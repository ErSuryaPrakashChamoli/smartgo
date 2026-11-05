# SmartGro — OD-158: CSP hardening, the local site service and retiring the demo

Report of 2026-10-31, 16:50 to 18:10 IST. Phase 14 item P14-16. Evidence: `evidence/phase14/2026-10-31-od158/`.

**Boundaries kept:**
- The policy is report-only and off by default. Enforcement was neither built nor sent.
- Nothing was committed, pushed or deployed.
- Nothing was written to `smart_go` or `smartgro_preview`.

## Summary

| Status | Item |
|---|---|
| **Implemented, tested and passed** | Per-response nonces in report mode. Separate public and back-office policies. Nonces on the 3D viewer's own script. `blob:` images for upload previews in the back office. The navigation's two inline styles moved into the stylesheet (A-31 option a). Expanded `verify-csp.mjs` with a drop-down menu fixture. Pest tests for each; the nonce, policy split, navigation and viewer tests were also mutation-checked. |
| **Verified and documented** | The site on 8080 (service, environment, sample switch). The demo on 8768 retired (nothing ran; no startup entry existed; its instructions removed). `LOCAL_DEVELOPMENT.md` rewritten from verified facts. |
| **Implemented but not fully tested** | The `LIVEWIRE_CSP_SAFE` switch (default off), and the policy leaving out `'unsafe-eval'` when it is on: tested by Pest. Livewire's CSP-safe build itself breaks Filament 5 (§D experiment), so it stays off. Browser coverage is Chromium only. |
| **Blocked or found, not fixed (out of scope)** | P14-17: the back-office media list renders thumbnails with an empty `src` (pre-existing since `7905e2e`, 2026-10-26; not a policy matter). |
| **Requires owner approval** | A-32: how the back office allows Filament's five un-nonced `<style>` elements (hashes or `'unsafe-inline'`). Enforcement itself (A-31, remaining part). A-22, A-23, A-27 to A-30 unchanged. |

## A. Initial state (16:5x IST, before any change)

| Item | Fact |
|---|---|
| Branch, HEAD | `main`, `832549382a367d686d9c0981471f2ce66f5270d4` ("update oct 31"); `5ef20f4` and `ee9083c` below it, unchanged. Three commits ahead of the last-known `origin/main`; the remote was not contacted. |
| Staged | nothing |
| Unstaged | `app/Http/Middleware/SetSecurityHeaders.php`, `DECISION_REGISTER.md`, `LOCAL_DEVELOPMENT.md`, `SPACES_ACTIVATION_PROCEDURE.md`, `run-checks.sh`, `PHASE_STATUS.md`, `SECURITY_MODEL.md`, `PHASE_14_ACCEPTANCE_PACK.md`, `SetSecurityHeadersTest.php` (OD-156 and OD-157 work) |
| Untracked | `config/security.php`, `verify-csp.mjs`, the OD-156 report and evidence, the OD-157 evidence (`2026-10-31-p14-16-csp/`) |
| Prior records | OD-157 (register 0.77; status log; pack §11; A-31) |
| Verified against the code | OD-157's claims hold: `CSP_MODE` defaults to `off`; only `report` sends `Content-Security-Policy-Report-Only`; the back office had `frame-ancestors 'none'`; there were 5 new tests and `verify-csp.mjs`; the evidence shows 83 page loads and 2,642 reports. |

## B. CSP implementation

**Default behaviour (tested):**
- Without `CSP_MODE=report` there is no policy header of either kind, and no nonce is generated, so the markup is unchanged.
- `off`, `enforce`, `Report`, `true` and an empty value all count as off.
- No code path sends an enforcing `Content-Security-Policy` header.
- The baseline headers (`X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, `X-Robots-Tag`, HSTS in production) are unchanged and still present in report mode.
- A route's own report-only policy is kept.
- The owner's server, the site and the preview set no `CSP_MODE`, and `bootstrap/cache/config.php` has no `security` key, so they send nothing.

**Report-only behaviour.** `SetSecurityHeaders` calls `Vite::useCspNonce()` before the page renders. The nonce therefore reaches:
- Laravel's Vite tags, including the `@fonts` style;
- Filament's inline scripts and Livewire's script tag. Livewire's JavaScript also rewrites nonces in morphed HTML to the page's own.
- the 3D viewer's script, which now reads `Vite::cspNonce()` as Filament's views do.

Each response gets a new nonce.

**Public policy:**

```
default-src 'self'; script-src 'self' 'report-sample'; style-src 'self' 'nonce-…' 'report-sample'; img-src 'self' data:; font-src 'self';
connect-src 'self'; media-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
```

**Back-office policy:**

```
default-src 'self'; script-src 'self' 'nonce-…' 'unsafe-eval' 'wasm-unsafe-eval' 'report-sample'; worker-src 'self' blob:;
style-src 'self' 'nonce-…' 'report-sample'; style-src-attr 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self';
connect-src 'self'; media-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'
```

With Livewire's `csp_safe` build, `'unsafe-eval'` is left out (see below).

**Nonce verification:**
- **Tests:** on five public templates (home, a CMS page, `/locations`, `/workspaces`, the 404 page), every `nonce=""` equals the policy's nonce, the `@fonts` style carries it, and five responses gave five different nonces. The back-office sign-in page's nonces equal its policy's. The 3D viewer's script carries the page's nonce.
- **Browser (run 5):** 137 documents, 1,462 nonce-carrying elements, no mismatch, no two documents sharing a nonce, no enforcing header on any response.
- **Caching:** the application caches no pages, and public pages send `Cache-Control: no-cache, private` (checked on 8080: `/`, `/about`, `/locations`), so no shared cache reuses a nonce. A CDN in front of production must not cache HTML (D-14).

**Each requirement, checked against the evidence (OD-157's strict policy, then this round's runs):**

| Directive | What needs it | Genuine? | Narrower alternative | Regression check | Status |
|---|---|---|---|---|---|
| public `style-src` nonce | the `@fonts` `@font-face` style (Laravel's Vite) | yes: 58 of 58 public loads in OD-157 | none needed: the nonce is the narrow form | Pest (5 templates), browser (0 reports) | resolved |
| public inline script | Laravel Boost's browser logger | development only: Boost is a development dependency and injects only with `APP_ENV=local` or debug on | not allowed in the policy, by design | browser: classified "development only" | stays reported locally; never in production |
| public style attributes | the navigation bar's `anchor-name` / `position-anchor` | was genuine for menus with drop-downs | moved to the stylesheet (§C) | Pest (no `style=` in the header), browser (0 `style-src-attr` reports with two drop-downs) | resolved (A-31 a) |
| back-office `script-src` nonce | Filament's inline scripts (67 reports in OD-157), Livewire's script | yes | the nonce is the narrow form | Pest (sign-in page), browser (0 inline-script reports) | resolved |
| back-office inline script | the 3D viewer's own `<script type="module">` | yes: an application tag without a nonce | add the nonce | Pest (TwinModelScreensTest), browser (0 reports) | **fixed this round** |
| `'unsafe-eval'` | Alpine in Livewire's standard build (1,579 reports in OD-157) | yes | Livewire's `csp_safe` build: tried on the test server, and it **breaks Filament 5**: uncaught "CSP Parser Error" on 26 of 27 back-office screens; the user menu, filters, saving and uploads stop | Pest (left out when `csp_safe` is on); experiment run | stays (justified); try the build again after a Filament upgrade |
| `'wasm-unsafe-eval'` | the Meshopt decoder (3D viewer) | yes (OD-157) | none: WebAssembly needs it; narrower than `'unsafe-eval'` | browser: the Meshopt model is shown, 0 reports | stays |
| `worker-src 'self' blob:` | the Draco decoder's worker (three.js) | yes (OD-157) | none without changing three.js | browser: the Draco model is shown, 0 reports | stays |
| `style-src-attr 'unsafe-inline'` | Filament's layout values (742 reports in OD-157; some set by `livewire.js`) | yes: attributes cannot carry a nonce | none practical: the values vary per field and screen | browser: 0 attribute reports | stays (justified) |
| back-office `<style>` elements | Filament's layout: `[x-cloak]`, theme colours, font and sidebar variables, an empty `:root`; the rich editor's `.ProseMirror` style | yes: 113 reports on 26 documents in run 5; Filament's views and Tiptap give them no nonce | (1) hashes of the five blocks: narrow, but they change when Filament or the panel's colours, fonts or sidebar width change; (2) `'unsafe-inline'` for style elements only, which means no nonce in `style-src` | browser: classified "framework, owner decision" | **A-32, owner decision** |
| back-office `img-src blob:` | FilePond's preview of an image chosen for upload | yes: 1 report in run 1 | none: the preview is made in the page | browser: preview shown, 0 reports | **added this round** |
| `frame-ancestors 'none'` | the back office may not be framed | working: Chrome reported it for `/admin/login` framed by a public page; `/about` framed by `/about` was allowed | — | browser (framing test), Pest | as intended |

## C. Navigation CSS refactor (A-31 option a)

**Files changed:**
- `resources/views/components/site/navigation/bar.blade.php`: the two `style` attributes were removed.
- `resources/css/app.css`: inside the existing `@supports (anchor-name: --a)` block, seven pairs `[popovertarget='nav-group-N'] { anchor-name }` and `#nav-group-N { position-anchor }`, N = 0 to 6. `NavigationPayload` allows the main menu at most seven items (`max:7`, `NavigationMenu::STRUCTURES`), so these cover every possible drop-down.
- `public/build` was rebuilt with `npm run build`. It is git-ignored, and the owner's server serves it live.

**Behaviour preserved (browser, runs 4 and 5, two drop-downs, one with sub-groups):**
- At 1024 and 1440 px each panel's computed `anchor-name` / `position-anchor` is its own, and it opens 12 px under its button (as `anchor(bottom) + 0.75rem` gives).
- The second sub-group tab shows its links and hides the first.
- Escape closes the panel; Enter on the focused button opens it.
- At 360 px the phone menu opens; all four groups (one with two sub-groups) open with their links; Escape closes it.
- No console or uncaught error.

Browsers without anchor positioning keep the previous centred panel.

**Tests:**
- `PublicShellTest` "anchors each drop-down to its own button with no inline style…" checks the markup of two groups, sub-group tabs, and no `style=` in the header. It was mutation-checked: putting one attribute back fails it.
- The 21 existing shell tests pass.
- - `CHECKS=public` with the policy off (`public/`) gave exit 0: 116 page loads, 0 failures (axe, overflow, console errors, menus without JavaScript, keyboard focus, error pages, sitemap). Its fixture menu has no drop-down, so this run only shows that nothing else changed; the drop-downs themselves are checked by `CHECKS=csp`.

**Remaining concern:** the Phase 2 design previews (`/design/foundations`, behind `EnsureDesignPreviewIsAvailable`) still use `style` attributes for colour swatches. They are not served in production.

**Incident during the mutation check:** the template was restored with `cp -p`, which kept its old modification time. Blade then kept serving the view compiled during the mutation from the shared `storage/framework/views`, from about 16:58 until it was found at 17:18 by a test failing. During that time the owner's server (port 8000, started 16:53, same storage) would have rendered the old markup, which is functionally identical: the previous `style` attribute.
- **Fixed:** the template was touched and recompiled; none of the compiled views contain the attribute now.
- **Unaffected:** the site and the preview have their own compiled views.

## D. Browser verification

**Mechanism:** `CHECKS=csp CHROMIUM_EXECUTABLE=/snap/bin/chromium TMPDIR=$HOME/snap/chromium/common/tmp bash docs/operations/scripts/browser/run-checks.sh <dir> 8767`. Headless Chromium 156.0.8078.47 on `smartgro_testing` (target and uncached configuration checked by `check-target.php`).

The runner publishes two things first, on the test database only:
- a fixture privacy notice;
- a main menu with two drop-downs (`seed-test-fixtures.php navigation-groups`).

`CHECKS=csp` still refuses to combine with other checks, and they keep `CSP_MODE=off`.

**Runs:**

| Run | Result | Why kept |
|---|---|---|
| 1 | exit 1: six follow-up steps had wrong selectors; found the 3D viewer script and `blob:` previews | `csp-run1-check-steps-failed/` |
| 2 | interrupted (SIGTERM) when the Claude session restarted | `csp-run2-interrupted-by-session-restart/` |
| 3 | exit 1: two steps (a cluster redirect; the table container's class) | `csp-run3-two-check-steps-failed/` |
| 4 | exit 1: only the media thumbnails (P14-17), now recorded rather than failed; 0 unexpected reports | `csp-run4-thumbnail-step-failed/` |
| 5 (final) | **exit 0**: 130 visits, 137 documents, 221 reports, 0 unexpected, 0 console errors, 0 failed requests, 1,462 nonced elements all matching | `csp/` |
| Experiment | `LIVEWIRE_CSP_SAFE=true`: exit 1, Filament broken (below) | `csp-livewire-csp-safe/` |
| Public regression | `CHECKS=public`, policy off: exit 0 | `public/` |

**Scenarios (run 5, the final run):**

| Scenario | Exercised | Result | Reports | Console errors / failed requests |
|---|---|---|---|---|
| 32 public addresses at 360, 768 and 1440 px (home, locations, workspace types, comparison, solutions and community, content, legal, insights, customers, enquiry and tour forms, 404, 410) | yes, 96 loads | as expected | only Boost (development only) | 0 / 0 |
| Navigation drop-downs (1024, 1440) and the phone menu (360) | yes | §C | none | 0 / 0 |
| Fonts | yes, home at 1440 | Inter 400/500/600, Sora 600 and Playfair Display 600 loaded; none failed | none | 0 / 0 |
| Images | yes, media page | its image loaded | none | 0 / 0 |
| Enquiry refused (invalid email, 360) and sent (1440) | yes | refused with 3 messages; sent and redirected to `/enquire/received` (`form-action 'self'`) | none | 0 / 0 |
| Redirects | yes | `/admin` without a session goes to `/admin/login`; a cluster link 302s to its first page; the enquiry POST redirects | none | 0 / 0 |
| Error pages | yes | 404 and 410 as expected (OD-157 also had the back-office 403 and 404 pages) | Boost only | 0 / 0 |
| Framing | yes | `/admin/login` framed by `/about`: `frame-ancestors 'none'` reported; `/about` framed by itself: allowed | 4 `frame-src` (made by the test itself) | 0 / 0 |
| Back-office sign-in | yes, a wrong password | "These credentials do not match our records" | Filament styles only (A-32) | 0 / 0 |
| A real sign-in | **no** | fixture staff have random passwords and email codes; screens use fixture sessions | — | gap |
| Navigation and menus | yes | the user menu (2 items); a sidebar link (`/admin/properties`, which redirects to `/admin/properties/properties`) | A-32 only | 0 / 0 |
| Livewire and Alpine | yes | filters dropdown, a column sort, a list search: Livewire 200 each | A-32 only | 0 / 0 |
| Form validation | yes | a new page created empty: the browser's own required-field check first (2 fields); with it bypassed, 2 server messages | A-32 only | 0 / 0 |
| Form submission | yes | a city saved ("Saved"); test database only | A-32 only | 0 / 0 |
| Upload and preview | yes | an image uploaded (FilePond, Livewire's temporary upload), preview shown | none since `blob:` was added | 0 / 0 |
| Media list thumbnails | yes | **no thumbnail address** (P14-17) | none | 0 / 0 |
| Document previews | **no** | the back office has no document preview | — | not applicable |
| Rich text editor | load only | its style reported (A-32); typing not exercised | A-32 | 0 / 0 |
| 3D viewer, Meshopt and Draco | yes | both "Model shown" | none | 0 / 0 |
| 16 other back-office screens, and the back-office 403 and 404 pages | yes | as expected (403, 404) | A-32 only | 0 / 0 |

**All reports of run 5: 221, none unexpected.**

| Kind | Reports | Directive | What |
|---|---|---|---|
| Development only | 104 | public `script-src-elem` | Boost's logger on every public load (`'self'` only) |
| Caused by the check | 4 | public `frame-src` | the framing test's own frames |
| Framework, owner decision | 113 | back-office `style-src-elem` | Filament's four layout styles on 26 back-office documents (one of them the sign-in page framed by the framing test), and the rich editor's style |

**Not covered:**
- Browsers other than Chromium: no Firefox, no Safari (`wasm-unsafe-eval` and `style-src-attr` support differ).
- A real sign-in and two-step login.
- Modals and confirmation dialogs.
- Typing in the rich editor and the block builder's add-block menu.
- The inventory workbook upload itself (only its form).
- Every other screen and widget not in the list.
- Real content and traffic, and staging with its CDN and proxy (D-14).

**Experiment: Livewire's CSP-safe Alpine, the narrower alternative to `'unsafe-eval'`.**
- **Run:** `LIVEWIRE_CSP_SAFE=true CHECKS=csp …` on the test server only. Livewire then served its CSP build (`livewire.js?id=…-csp`), and the back-office policy had no `'unsafe-eval'`.
- **Result:** 26 of the 27 back-office visits had uncaught errors, 624 in all:
  - "CSP Parser Error: Expected PUNCTUATION ':'" 467 times;
  - "Undefined variable: JSON" 114 times;
  - others, such as `getSelectedRecordsCount` and `isUploadingFile`.
- **Broken steps:** the user menu, the filters, a save and the image upload.
- **Unaffected:** public pages (no Alpine) and the staff preview.
- **Conclusion:** Filament 5's views use expressions Alpine's CSP build cannot parse, so `'unsafe-eval'` is required with the current Filament. The switch stays off, kept only to repeat this check after an upgrade.

**Tests and checks run** (all on `smartgro_testing`: `phpunit.xml` forces it, `tests/TestCase.php` refuses any other, the kit's `check-target.php` confirms it; caches bypassed with `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE` and `APP_EVENTS_CACHE` set to missing files):

| Command | Exit | Result |
|---|---|---|
| `vendor/bin/pest --compact tests/Feature/Http/Middleware/SetSecurityHeadersTest.php tests/Feature/Filament/Clusters/Website/Resources/TwinModels/TwinModelScreensTest.php tests/Feature/Http/Controllers/Web/PublicShellTest.php` | 0 | 43 passed, 282 assertions (after the fixes below) |
| Mutation checks: nonce always generated; one nonce for every response; back office given the public policy; a `style` attribute back in `bar.blade.php`; the viewer's nonce removed | — | each failed at least one test; files restored byte for byte (and the template touched) |
| `vendor/bin/pest --compact tests/Feature/Filament tests/Feature/Http tests/Feature/Services tests/Feature/Views` | 0 | **701 passed, 3,919 assertions**, 0 failed, 0 skipped (17:52–18:01) |
| `vendor/bin/pest --compact tests/Feature/{Console,Database,Listeners,Models,Policies,Providers,Security} tests/Feature/ExampleTest.php tests/Unit tests/Arch` | 0 | **241 passed, 748 assertions**, 0 failed, 0 skipped (18:01–18:04) |
| `CHECKS=csp … run-checks.sh …/csp 8767` (run 5) | 0 | as above |
| `LIVEWIRE_CSP_SAFE=true CHECKS=csp …` | 1 | Filament broken (experiment) |
| `CHECKS=public …` (policy off) | 0 | 116 page loads, 0 failures |
| `vendor/bin/pint --dirty --format agent` | 0 | passed |

**Failures along the way, and why:**
- A pattern in the new navigation test assumed no space before `>`.
- The viewer test's pattern assumed no space around `nonce=`.
- The stale compiled view described in §C.

All three were test-side issues; none was a regression. Not run: `tests/Feature/Actions` (354) and `tests/Feature/Infrastructure` (29), which this round's changes do not reach. **The full suite should be run by the owner** (`php artisan test --compact`, with the cache overrides).

## E. Port 8080: the local site

**Process.** `127.0.0.1:8080` belongs to PHP's built-in server: PID 4096 with four workers (4158 to 4161), started at 15:47:40 IST. Its parent is the user's systemd instance (4056), in cgroup `app.slice/smartgro-site.service`.

**Unit file.** `~/.config/systemd/user/smartgro-site.service` (created in OD-147, 2026-10-30): `enabled`, `active`, `Restart=on-failure`, `NRestarts=0`, `WantedBy=default.target`.

**Start history.** The journal shows it started at 2026-10-30 20:31:38 (OD-147) and at 2026-10-31 15:47:40, 19 seconds after this boot (15:47:21). Lingering is off (`Linger=no`), so it starts with the user's session, not at a boot without login.

**Environment.** The unit supplies:
- `DB_DATABASE=smartgro_site`;
- `APP_URL=http://127.0.0.1:8080`;
- `LARAVEL_STORAGE_PATH=/home/administrator/smartgro-site/storage`;
- the three cache-bypass paths;
- `PHP_CLI_SERVER_WORKERS=4`.

`BACKOFFICE_SAMPLE_CONTENT` and `CSP_MODE` are **absent** from the running process's environment (read from `/proc/4096/environ`; no secrets printed).

**Effective configuration.** Resolved with the unit's variables (artisan, a scratch storage folder): database `smartgro_site`, `backoffice.sample_content` = **false** (from `.env`), `security.csp_mode` = `off`, `APP_ENV=local`, configuration not cached.

**Behaviour.** Sample pages and articles cannot be submitted or published there. Drafts can be saved, and the published sample content stays visible. This keeps the safeguard; it was not changed.

**Responds.** `/`, `/about` and `/locations` returned 200, with no CSP header, at about 17:0x and 17:3x.

**Discrepancy fixed in the documentation.** `LOCAL_DEVELOPMENT.md` said the site ran in a transient `smartgro-8080` scope with `BACKOFFICE_SAMPLE_CONTENT=true` that does not survive a restart. Its new "Local servers and databases" and "Local site on port 8080" sections describe the service as verified. The OD-152/OD-153 scope instructions are removed.

## F. Port 8768: the demo, retired

**Process.** Nothing listened on 8768 throughout (`ss`), and no process had `DB_DATABASE=smartgro_demo` (`/proc/*/environ` of the user's processes).

**Startup mechanism.** No `smartgro-8768` scope existed. There is no unit file, `default.target.wants` link, timer, cron entry, autostart entry or shell-profile line for it. The demo was only ever started by hand; most recently, the `systemd-run --scope` command in `LOCAL_DEVELOPMENT.md`.

**Stopped.** Nothing needed stopping, and nothing was terminated.

**Removed:**
- the demo's start command and stop instructions in `LOCAL_DEVELOPMENT.md`;
- in `SPACES_ACTIVATION_PROCEDURE.md` step 0, "the demo and site … can keep running" now names only the site and says the demo is retired.

**Kept:**
- the `smartgro_demo` database;
- `/home/administrator/smartgro-demo/` (storage, logs, login file, dump);
- earlier evidence;
- `seed-sample-content.php` (it still accepts `smartgro_demo`).

**Verification:**
- 8768 is not listening (final check §G).
- No startup entry exists.
- 8080 responds (§E).
- No process was terminated.

## G. Safety verification

**`smart_go`:**
- Read-only fingerprints only (`READ ONLY` transactions) at 17:20 and 18:04.
- Compared with OD-156's end (15:35), the only difference is **one new guest session row at 16:54:37 IST**: desktop Chrome, loopback, not headless. It came from the owner's own SmartGro server, `php artisan serve` started at 16:53:00 from this project with no database override (cached configuration: `smart_go`), which took **port 8000** because the HRMS project took 8001 at 16:54:29.
- This session sent no request to 8000 or 8001.
- **A second guest session at 17:46:21 IST** has user agent `curl/8.18.0` (loopback), on the same server on 8000. It is not from this session: all its `curl` requests went to 8080 (`smartgro_site`) or 8767 (the kit's server, `smartgro_testing`), and none ran at that moment. Its source is unknown, perhaps another local tool or session.
- At 18:04 every table other than `sessions` (9 rows at 15:35, 11 at 18:04) equals OD-156's end: the migration, permissions, space types and pivot are unchanged.

**`smartgro_preview`:**
- Read-only fingerprints at 17:20 and 18:04 are **equal** (64 tables).
- 68 migrations, as in OD-151.
- Its server was not running and was not started.

**Test database:**
- Every Pest run and kit run used `smartgro_testing`. `phpunit.xml` forces it and `tests/TestCase.php` refuses any other; the kit's `check-target.php` printed `connected_database: smartgro_testing`, `config_cached: false`.
- Laravel Boost: this project's MCP process (PID 82946) has `CACHE_STORE=array` and the never-created configuration-cache path (`.mcp.json`).

**Sample content:**
- `.env` still has `BACKOFFICE_SAMPLE_CONTENT=false`, and the site's unit is unchanged.
- Nothing was published or submitted as sample content anywhere. The kit's fixture privacy notice and menu are labelled TEST FIXTURE and exist only on `smartgro_testing`.

**Processes:**
- None was stopped or restarted. The site kept PID 4096 with `NRestarts=0`.
- The kit's own server on 8767 was started and stopped by `run-checks.sh`. Run 2's was stopped by the session restart.

**The owner's server and this round's edits.** The owner's server reads this working tree. Two kinds of change reached it:
- **The intended changes:** the navigation CSS (and the rebuilt `public/build`), and the viewer's nonce attribute, which is empty without a nonce.
- **Brief mutation-check edits** to `SetSecurityHeaders.php`, `bar.blade.php` and `viewer.blade.php`, seconds each, restored byte for byte. With the policy off, they could only add nonce attributes, or briefly show the old navigation attribute (§C).

Nothing reached its data.

## H. Git state (end)

- **Commits:** none made; HEAD is still `8325493`; nothing staged; nothing pushed.
- **This round's changes:**
  - `app/Http/Middleware/SetSecurityHeaders.php`, also changed by OD-157;
  - `config/livewire.php` (new `csp_safe` key);
  - `resources/css/app.css`, `resources/views/components/site/navigation/bar.blade.php` and `resources/views/filament/twin-models/viewer.blade.php`;
  - `tests/Feature/Http/Middleware/SetSecurityHeadersTest.php` (OD-157's CSP tests replaced by stronger ones covering the same assertions), `tests/Feature/Http/Controllers/Web/PublicShellTest.php` and `tests/Feature/Filament/Clusters/Website/Resources/TwinModels/TwinModelScreensTest.php`;
  - `docs/operations/scripts/browser/verify-csp.mjs`, `run-checks.sh` and `seed-test-fixtures.php`;
  - `docs/operations/LOCAL_DEVELOPMENT.md`, plus one line in `docs/operations/SPACES_ACTIVATION_PROCEDURE.md` (which also has OD-156's revision);
  - `docs/testing/PHASE_14_ACCEPTANCE_PACK.md`, `docs/security/SECURITY_MODEL.md`, `docs/decisions/DECISION_REGISTER.md` and `docs/phases/PHASE_STATUS.md`;
  - this report and `docs/testing/evidence/phase14/2026-10-31-od158/`;
  - `public/build`, rebuilt (git-ignored).
- **Earlier uncommitted work, preserved:** OD-156's report, evidence and procedure revision; OD-157's `config/security.php`, evidence and document edits. Nothing was reset, stashed, cleaned or overwritten.
- **Totals:** 16 tracked files modified (+642/−126 across all uncommitted work); 7 untracked entries.
- **Secrets:** a scan of the new evidence and documents for `.env`'s key and password values and for session cookies found none.

## I. Outstanding decisions

| ID | Status |
|---|---|
| A-22, A-23 | open: Spaces activation on `smart_go`; not touched |
| A-27 | open: a second backup destination off this machine; none supplied |
| A-28 | open: both Spaces combinations; not touched |
| A-29 | open: `8325493` and the inventory commit gate; no push |
| A-30 | open, recommended: `chmod 600 bootstrap/cache/config.php` (still 664) |
| A-31 | option (a) done (§C). Enforcement is not authorised and not built. |
| **A-32 (new)** | the back office's style elements: (1) hashes of Filament's five style blocks, kept in step with Filament upgrades and panel changes; or (2) `style-src 'self' 'unsafe-inline'` for the back office, with no nonce for styles. Recommended: (2). CSS cannot run script, and `img-src`, `font-src` and `connect-src` stay `'self'`, which limits CSS-based data leaks; (1) would break the back office's styles silently at the next Filament update. Livewire's CSP-safe build does not change this: its run reported the same style elements. |
| P14-17 (finding) | the media list's thumbnails: `MediaAsset::renditionPath()` returns a relative route, which `ImageColumn` treats as a file on the `local` disk; it finds none and renders `src=""`. Proposed fix: give the column an absolute URL (`route(…, absolute: true)`, or a `url()` accessor) with a test; not made, as it is outside OD-158's scope. |

## J. Next step

1. **The owner decides A-32,** and whether to pursue Livewire's CSP-safe build (§D experiment).
2. **Then the enforcement proposal below** can be made concrete. It is not ready while A-32 is open, other browsers are untested, and staging does not exist.
3. **Next safe action:** with A-32 decided, rerun `CHECKS=csp` to zero unexpected and zero undecided reports. Fix P14-17 when authorised.

## K. Enforcement proposal (for a later, separately authorised change; nothing here is active)

1. **Policies:** public and back office as in §B, with A-32's choice in the back office's `style-src`, and `'unsafe-eval'` unless the CSP-safe build is adopted.
2. **Broad exceptions:**
   - `'unsafe-eval'`: Alpine's standard build (1,579 reports);
   - `style-src-attr 'unsafe-inline'`: Filament's layout attributes (742);
   - A-32's choice: Filament's five style blocks.
   - The narrow exceptions (`'wasm-unsafe-eval'`, `worker-src blob:`, `img-src blob:`) each have one named cause.
3. **Representative results:** run 5 (§D): 130 visits, 137 documents, 221 reports, all explained, 0 console errors, 0 failed requests.
4. **Outstanding violations:** only A-32's (113 per run), plus Boost's locally.
5. **Public site:** compatible now. Its only inline style carries the nonce and it has no inline script; Boost is absent in production.
6. **Filament, Livewire and Alpine:** compatible with nonces; needs `'unsafe-eval'` (standard build) and A-32.
7. **Navigation and phone menu:** compatible (§C).
8. **Forms, uploads and previews:**
   - The enquiry form and back-office forms are compatible.
   - Image upload previews are compatible with `img-src blob:`.
   - Not checked: the workbook upload, typing in the rich editor and modals.
9. **Fonts:** compatible; all same-site, and `@fonts` carries the nonce.
10. **3D viewer:** compatible with `'wasm-unsafe-eval'` and `worker-src blob:`.
11. **Development and production:**
    - Boost's logger appears only locally.
    - Vite's development server (`npm run dev`) would need its own origin; the kit uses the build.
    - Production caches configuration, so set `CSP_MODE` before `config:cache`.
12. **Rollout:**
    - (a) A-32 decided.
    - (b) Report-only on staging with a reporting endpoint chosen at D-14, for at least two weeks of real use, in Chromium, Firefox and Safari.
    - (c) Enforce the public site first.
    - (d) Then the back office.
13. **Monitoring:** no unexpected report for a week of staging traffic; then, after each enforcement step, no report and no console error in the kit and the staging logs.
14. **Rollback:** set `CSP_MODE` back to `report` or `off` and rebuild the configuration cache. There is no deployment and no data change. The enforce mode itself is a separate reviewed code change.
15. **Owner approvals needed:**
    - A-32;
    - the CSP-safe build, or `'unsafe-eval'`;
    - building the enforce mode;
    - staging (D-14) and the reporting endpoint;
    - each enforcement step.
