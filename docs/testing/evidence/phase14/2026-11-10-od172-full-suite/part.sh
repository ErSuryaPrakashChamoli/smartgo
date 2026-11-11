#!/usr/bin/env bash
# Usage: part.sh <name> <paths...> — one part of the suite on smartgro_testing, with the cache overrides.
set -u
S=/tmp/claude-1000/-home-administrator-Documents-website-smartGrow/50d33888-3a19-4d6c-9b68-312c7cd6c4c3/scratchpad/suite
name=$1; shift
cd /home/administrator/Documents/website/smartGrow
export APP_CONFIG_CACHE=$S/none-config.php APP_ROUTES_CACHE=$S/none-routes.php APP_EVENTS_CACHE=$S/none-events.php
start=$(date '+%F %T'); load=$(cut -d' ' -f1-3 /proc/loadavg)
timeout 580 php artisan test --compact --log-junit "$S/$name.xml" "$@" > "$S/$name.log" 2>&1
code=$?
end=$(date '+%F %T')
echo "{\"part\":\"$name\",\"start\":\"$start\",\"end\":\"$end\",\"load_at_start\":\"$load\",\"exit\":$code,\"testing_db\":$(php $S/testing-db-check.php)}" | tee "$S/$name.meta.json"
tail -c 600 "$S/$name.log"
