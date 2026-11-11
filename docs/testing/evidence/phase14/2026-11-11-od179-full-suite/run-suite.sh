#!/usr/bin/env bash
# OD-179: the whole suite once, serially, on smartgro_testing (phpunit.xml forces it; TestCase refuses any other
# database), with the configuration, route and event caches bypassed and no FILAMENT_CACHE_PATH (OD-172 c).
# Records HEAD, the working tree's status and checksums of any changed or untracked file before and after.
set -u
S=/tmp/claude-1000/-home-administrator-Documents-website-smartGrow/ca7ac759-3e82-49da-a28c-4d4b424b87f3/scratchpad
cd /home/administrator/Documents/website/smartGrow
checksums() { git status --porcelain --untracked-files=all | sed 's/^...//' | sort | while read -r f; do [ -f "$f" ] && sha256sum "$f"; done; }
checksums > "$S/suite/tree-before.sha256"
{ git rev-parse HEAD; git status --porcelain --untracked-files=all; } > "$S/suite/git-before.txt"
ls bootstrap/cache > "$S/suite/bootstrap-cache-before.txt"
export APP_CONFIG_CACHE=$S/none-config.php APP_ROUTES_CACHE=$S/none-routes.php APP_EVENTS_CACHE=$S/none-events.php
unset FILAMENT_CACHE_PATH
echo "start $(date '+%F %T') load $(cut -d' ' -f1-3 /proc/loadavg) head $(git rev-parse --short HEAD)" > "$S/suite/times.txt"
php artisan test --compact --log-junit "$S/suite/full.junit.xml" > "$S/suite/full.log" 2>&1
code=$?
echo "end $(date '+%F %T') load $(cut -d' ' -f1-3 /proc/loadavg) exit $code" >> "$S/suite/times.txt"
checksums > "$S/suite/tree-after.sha256"
{ git rev-parse HEAD; git status --porcelain --untracked-files=all; } > "$S/suite/git-after.txt"
ls bootstrap/cache > "$S/suite/bootstrap-cache-after.txt"
cmp -s "$S/suite/git-before.txt" "$S/suite/git-after.txt" && cmp -s "$S/suite/tree-before.sha256" "$S/suite/tree-after.sha256" && echo "tree unchanged" >> "$S/suite/times.txt" || echo "tree CHANGED" >> "$S/suite/times.txt"
exit $code
