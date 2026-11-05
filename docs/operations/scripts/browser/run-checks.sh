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
#   4. Runs verify-public.mjs, verify-enquiry.mjs, verify-admin.mjs and verify-twin-performance.mjs (Playwright and axe-core
#      from the npx cache). The last takes a few minutes: it loads a 15 MB model on a throttled network.
#      Between them, "enquiry" publishes a fixture privacy notice on the test database (it opens the
#      enquiry form there only) and runs verify-enquiry.mjs on the form and its confirmation page, and on
#      a page built, approved and published in the back office with a location list and the form (OD-125).
#      Then "tour" runs verify-tour.mjs (OD-129): a visitor requests a tour of A-70 (fixture tour hours),
#      the Super Admin confirms it, the visitor sees it confirmed, downloads its calendar file, moves it and cancels it.
#      CHECKS limits the scripts run, for example CHECKS=performance (default: "public enquiry tour admin performance").
#      CHECKS=csp runs verify-csp.mjs on its own (P14-16): the server then sends the Content Security Policy as
#      report-only (CSP_MODE=report), a fixture privacy notice and a main menu with drop-downs are published
#      first, and the script lists and classifies what the policy would block. It cannot be combined with the
#      other checks, which count the browser's reports as console errors; for them the policy is off.
#   5. With LIGHTHOUSE=1, also runs lighthouse-local.sh (diagnostics only; not a performance verdict).
#   6. Writes public.json, admin.json, performance.json, csp.json, run.json (and lighthouse.json) into <evidence-dir>. Session
#      cookies, the temporary storage and the browser's own temporary files stay in a temporary folder
#      ($TMPDIR/smartgro-checks.*) that is deleted afterwards.
#
# run.json gives each script's exit code, "not run" when CHECKS did not select it, "interrupted" for the
# one running when the run was stopped (Ctrl-C, SIGTERM, SIGHUP; "interrupted_by" names the signal) and
# "not reached" for a selected one that never started. A stopped run ends the running script (its browser
# closes) and the server. When a run fails or is stopped, the server's request log and the application
# log are kept in <evidence-dir>/logs. Only SIGKILL skips all this: then remove the server on the port and
# the smartgro-checks.* folder by hand.
#
# Exit code: 0 when every check passes; otherwise the first failing step's code (130, 143 or 129 when stopped).

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

# The work folder is made under TMPDIR when it is set (the snap Chromium needs one in its own folder),
# so the clean-up accepts only a folder this script made there, not just one under /tmp.
tmp_root="${TMPDIR:-/tmp}"
tmp_root="${tmp_root%/}"
work="$(mktemp -d "${tmp_root}/smartgro-checks.XXXXXXXXXX")"
checks=" ${CHECKS:-public enquiry tour admin performance} "
target=""
server_pid=""
check_pid=""
current_check=""
interrupted=""
privacy_published=0
declare -A exit_codes=()

# A script's entry in run.json (see the header).
check_status() {
    if [[ "$checks" != *" $1 "* ]]; then
        echo "not run"
    elif [[ -n "${exit_codes[$1]+set}" ]]; then
        echo "${exit_codes[$1]}"
    elif [[ "$current_check" == "$1" ]]; then
        echo "interrupted"
    else
        echo "not reached"
    fi
}

write_run_json() {
    php -r '
        $commands = [];
        foreach (array_slice($argv, 5) as $entry) {
            [$script, $status] = explode("=", $entry, 2);
            $commands[$script] = is_numeric($status) ? (int) $status : $status;
        }
        echo json_encode([
            "finished_at" => date(DATE_ATOM),
            "git_head" => trim((string) shell_exec("git rev-parse --short HEAD")),
            "php" => PHP_VERSION,
            "node" => trim((string) shell_exec("node --version")),
            "database_check" => json_decode($argv[1], true),
            "server" => $argv[2],
            "checks" => $argv[3],
            "interrupted_by" => $argv[4] === "" ? null : $argv[4],
            "commands" => $commands,
        ], JSON_PRETTY_PRINT), PHP_EOL;
    ' "$target" "$base" "$(echo $checks)" "$interrupted" \
        "verify-public.mjs=$(check_status public)" "verify-enquiry.mjs=$(check_status enquiry)" "verify-tour.mjs=$(check_status tour)" \
        "verify-admin.mjs=$(check_status admin)" "verify-twin-performance.mjs=$(check_status performance)" "verify-csp.mjs=$(check_status csp)" > "$evidence/run.json"
}

# Stops a script still running (SIGTERM: Playwright closes its browser; killed after 20 seconds) and any
# browser started from this run's temporary folder, then the server; keeps the logs of a failed or
# stopped run; deletes the temporary folder.
cleanup() {
    local status=$?
    if [[ -n "$check_pid" ]] && kill -0 "$check_pid" 2>/dev/null; then
        kill -TERM "$check_pid" 2>/dev/null
        for _ in $(seq 1 40); do
            kill -0 "$check_pid" 2>/dev/null || break
            sleep 0.5
        done
        kill -KILL "$check_pid" 2>/dev/null
    fi
    pkill -KILL -f -- "--user-data-dir=${work}/" 2>/dev/null
    if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null; fi
    if [[ -n "$interrupted" ]]; then write_run_json; fi
    if [[ $status -ne 0 && -d "$work" ]]; then
        mkdir -p "$evidence/logs"
        cp "$work/server.log" "$work"/storage/logs/*.log "$evidence/logs/" 2>/dev/null
    fi
    if [[ -n "$work" && -d "$work" && "$work" == "${tmp_root}"/smartgro-checks.* ]]; then rm -rf "$work"; fi
}
stop() {
    interrupted="$1"
    exit "$2"
}
trap cleanup EXIT
trap 'stop INT 130' INT
trap 'stop TERM 143' TERM
trap 'stop HUP 129' HUP

# Each script runs in the background while this shell waits for it, so that a signal reaches the trap at
# once (bash runs a trap only after the command in the foreground ends). Its browser keeps its profile
# and downloads in this run's temporary folder.
run_check() {
    local name="$1"
    shift
    current_check="$name"
    TMPDIR="$work/tmp" node "$@" &
    check_pid=$!
    wait "$check_pid"
    exit_codes[$name]=$?
    check_pid=""
    current_check=""
}

# The enquiry and tour forms open only once a privacy notice is published: a fixture one, on the test
# database only. As with the fixtures, the output must be JSON.
publish_privacy() {
    if [[ $privacy_published -eq 0 ]] \
        && LARAVEL_STORAGE_PATH="$work/storage" php -d variables_order=EGPCS "$scripts/seed-test-fixtures.php" "$work/errors" privacy > "$work/privacy.json" \
        && php -r 'exit(is_array(json_decode((string) file_get_contents($argv[1]), true)) ? 0 : 1);' "$work/privacy.json"; then
        privacy_published=1
    fi
    if [[ $privacy_published -eq 0 ]]; then
        echo "Publishing the fixture privacy notice failed:" >&2
        cat "$work/privacy.json" >&2
    fi
    [[ $privacy_published -eq 1 ]]
}

mkdir -p "$work/storage/framework/views" "$work/storage/framework/cache" "$work/storage/framework/sessions" "$work/storage/logs" "$work/storage/app" "$work/errors" "$work/tmp"
export APP_CONFIG_CACHE="$work/no-config-cache.php" APP_ROUTES_CACHE="$work/no-routes-cache.php" APP_EVENTS_CACHE="$work/no-events-cache.php"
# Filament's own list of screens (bootstrap/cache/filament, written by `optimize`) would hide screens added
# since it was built, so the kit's server discovers them from the working tree (config/filament.php).
export FILAMENT_CACHE_PATH="$work/no-filament-cache"
export DB_DATABASE=smartgro_testing APP_URL="$base"
if [[ "$checks" == *" csp "* && "$checks" != " csp " ]]; then
    echo "Run CHECKS=csp on its own: the other checks count the policy's reports as console errors." >&2
    exit 1
fi
if [[ "$checks" == " csp " ]]; then export CSP_MODE=report; else export CSP_MODE=off; fi

target="$(EXPECT_DB=smartgro_testing php "$scripts/check-target.php")" || { echo "$target"; echo "Refusing: the test database is not the one reached." >&2; exit 1; }

LARAVEL_STORAGE_PATH="$work/storage" php -d variables_order=EGPCS "$scripts/seed-test-fixtures.php" "$work/errors" > "$work/fixtures.json" || { echo "Seeding the test database failed." >&2; exit 1; }
# Laravel's exception handler can end a failed seeding with exit code 0, so the output must be the fixture JSON.
if ! php -r 'exit(is_array(json_decode((string) file_get_contents($argv[1]), true)) ? 0 : 1);' "$work/fixtures.json"; then
    echo "Seeding the test database failed:" >&2
    cat "$work/fixtures.json" >&2
    exit 1
fi

# Several workers, as a production server has: with one, a long download (a 15 MB model on a throttled
# connection) holds up every other request, which no visitor would see.
(cd public && LARAVEL_STORAGE_PATH="$work/storage" PHP_CLI_SERVER_WORKERS=4 exec php -d variables_order=EGPCS -S "127.0.0.1:${port}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php > "$work/server.log" 2>&1) &
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

if [[ "$checks" == *" public "* ]]; then
    run_check public "$scripts/verify-public.mjs" "$base" "$evidence" "$work/errors"
fi
for name in enquiry tour; do
    if [[ "$checks" == *" $name "* ]]; then
        if publish_privacy; then
            run_check "$name" "$scripts/verify-$name.mjs" "$base" "$evidence" "$work/fixtures.json"
        else
            exit_codes[$name]=1
        fi
    fi
done
if [[ "$checks" == *" admin "* ]]; then
    run_check admin "$scripts/verify-admin.mjs" "$base" "$evidence" "$work/fixtures.json"
fi
if [[ "$checks" == *" performance "* ]]; then
    run_check performance "$scripts/verify-twin-performance.mjs" "$base" "$evidence" "$work/fixtures.json"
fi
# The forms open only once a privacy notice is published, and the fixture menu has no drop-down, so both are
# added first (test database only), so the policy is checked against them too.
if [[ "$checks" == *" csp "* ]]; then
    if publish_privacy \
        && LARAVEL_STORAGE_PATH="$work/storage" php -d variables_order=EGPCS "$scripts/seed-test-fixtures.php" "$work/errors" navigation-groups > "$work/navigation.json" \
        && php -r 'exit(is_array(json_decode((string) file_get_contents($argv[1]), true)) ? 0 : 1);' "$work/navigation.json"; then
        run_check csp "$scripts/verify-csp.mjs" "$base" "$evidence" "$work/fixtures.json"
    else
        echo "Preparing the CSP check failed:" >&2
        cat "$work/navigation.json" >&2 2>/dev/null
        exit_codes[csp]=1
    fi
fi

# Optional, diagnostics only (LIGHTHOUSE=1): local Lighthouse figures are not a performance verdict.
if [[ "${LIGHTHOUSE:-0}" == "1" ]]; then
    bash "$scripts/lighthouse-local.sh" "$base" "$evidence" / /locations/noida /locations/noida/a-70 /locations/noida/a-70/hot-desks "/locations/compare?locations[]=noida/a-70&locations[]=noida/h-105" /contact
fi

write_run_json
cat "$evidence/run.json"
for name in public enquiry tour admin performance csp; do
    status="$(check_status "$name")"
    [[ "$status" == "not run" || "$status" == 0 ]] || exit "$status"
done
exit 0
