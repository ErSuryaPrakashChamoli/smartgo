#!/usr/bin/env bash
#
# SmartGro: repeatable browser checks of the public website and the Phase 6 back-office screens,
# against the TEST database only.
#
# Run from the project root:   bash docs/operations/scripts/browser/run-checks.sh <evidence-dir> [port]
#
# What it does:
#   1. Confirms that the TEST database (`smartgro_testing`) is the one reached, with uncached
#      configuration (check-target.php); stops otherwise. `smart_go` is never used.
#   2. Rebuilds the test database with labelled fixtures (seed-test-fixtures.php), storing files (the
#      test-only 3D models) in the temporary storage folder. Every test run rebuilds the database too,
#      so do not run this while the test suite is running.
#   3. Starts a PHP server on 127.0.0.1:<port> (default 8767) with its own temporary storage folder
#      and cache paths, so a server you already run is not affected, and stops it at the end.
#   4. Runs verify-public.mjs, verify-admin.mjs and verify-twin-performance.mjs (Playwright and axe-core
#      from the npx cache). The last takes a few minutes: it loads a 15 MB model on a throttled network.
#   5. With LIGHTHOUSE=1, also runs lighthouse-local.sh (diagnostics only; not a performance verdict).
#   6. Writes public.json, admin.json, performance.json, run.json (and lighthouse.json) into <evidence-dir>. Session
#      cookies and the temporary storage stay in a temporary folder that is deleted afterwards.
#
# Exit code: 0 when every check passes; otherwise the first failing step's code.

set -uo pipefail

if [[ ! -f artisan ]]; then
    echo "Run this from the SmartGro project root." >&2
    exit 1
fi

evidence="${1:?Usage: run-checks.sh <evidence-dir> [port]}"
port="${2:-8767}"
base="http://127.0.0.1:${port}"
scripts="docs/operations/scripts/browser"
mkdir -p "$evidence"

if ss -ltn | grep -q ":${port} "; then
    echo "Port ${port} is in use; choose another." >&2
    exit 1
fi

work="$(mktemp -d)"
server_pid=""
cleanup() {
    if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null; fi
    if [[ -n "$work" && -d "$work" && "$work" == /tmp/* ]]; then rm -rf "$work"; fi
}
trap cleanup EXIT

mkdir -p "$work/storage/framework/views" "$work/storage/framework/cache" "$work/storage/framework/sessions" "$work/storage/logs" "$work/storage/app" "$work/errors"
export APP_CONFIG_CACHE="$work/no-config-cache.php" APP_ROUTES_CACHE="$work/no-routes-cache.php" APP_EVENTS_CACHE="$work/no-events-cache.php"
export DB_DATABASE=smartgro_testing APP_URL="$base"

target="$(EXPECT_DB=smartgro_testing php "$scripts/check-target.php")" || { echo "$target"; echo "Refusing: the test database is not the one reached." >&2; exit 1; }

LARAVEL_STORAGE_PATH="$work/storage" php -d variables_order=EGPCS "$scripts/seed-test-fixtures.php" "$work/errors" > "$work/fixtures.json" || { echo "Seeding the test database failed." >&2; exit 1; }
# Laravel's exception handler can end a failed seeding with exit code 0, so the output must be the fixture JSON.
if ! php -r 'exit(is_array(json_decode((string) file_get_contents($argv[1]), true)) ? 0 : 1);' "$work/fixtures.json"; then
    echo "Seeding the test database failed:" >&2
    cat "$work/fixtures.json" >&2
    exit 1
fi

(cd public && LARAVEL_STORAGE_PATH="$work/storage" exec php -d variables_order=EGPCS -S "127.0.0.1:${port}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php > "$work/server.log" 2>&1) &
server_pid=$!

for _ in $(seq 1 50); do
    curl -s -o /dev/null "$base/" && break
    sleep 0.2
done

fixture_page="$(curl -s "$base/locations/noida/a-70")"
if [[ "$fixture_page" != *"TEST FIXTURE headline for A-70"* ]]; then
    echo "The server does not serve the test fixtures; stopping." >&2
    exit 1
fi

node "$scripts/verify-public.mjs" "$base" "$evidence" "$work/errors"
public_exit=$?
node "$scripts/verify-admin.mjs" "$base" "$evidence" "$work/fixtures.json"
admin_exit=$?
node "$scripts/verify-twin-performance.mjs" "$base" "$evidence" "$work/fixtures.json"
performance_exit=$?

# Optional, diagnostics only (LIGHTHOUSE=1): local Lighthouse figures are not a performance verdict.
if [[ "${LIGHTHOUSE:-0}" == "1" ]]; then
    bash "$scripts/lighthouse-local.sh" "$base" "$evidence" / /locations/noida /locations/noida/a-70 /locations/noida/a-70/hot-desks "/locations/compare?locations[]=noida/a-70&locations[]=noida/h-105" /contact
fi

php -r '
    echo json_encode([
        "finished_at" => date(DATE_ATOM),
        "git_head" => trim((string) shell_exec("git rev-parse --short HEAD")),
        "php" => PHP_VERSION,
        "node" => trim((string) shell_exec("node --version")),
        "database_check" => json_decode($argv[1], true),
        "server" => $argv[2],
        "commands" => ["verify-public.mjs" => (int) $argv[3], "verify-admin.mjs" => (int) $argv[4], "verify-twin-performance.mjs" => (int) $argv[5]],
    ], JSON_PRETTY_PRINT), PHP_EOL;
' "$target" "$base" "$public_exit" "$admin_exit" "$performance_exit" > "$evidence/run.json"

cat "$evidence/run.json"
[[ $public_exit -eq 0 ]] || exit $public_exit
[[ $admin_exit -eq 0 ]] || exit $admin_exit
exit $performance_exit
