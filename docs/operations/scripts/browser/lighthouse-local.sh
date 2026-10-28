#!/usr/bin/env bash
#
# SmartGro: local Lighthouse diagnostics (mobile emulation, simulated throttling). A proxy only: the
# PHP development server sends no compression and no cache headers, so these figures do NOT show
# whether NFR-PERF-1/2 are met. Those targets are measured on staging (Phase 6 AC-6.2).
#
#   bash docs/operations/scripts/browser/lighthouse-local.sh <baseUrl> <outDir> <path>...
#
# Uses Lighthouse from the npx cache and Chromium from the Playwright cache (override with LIGHTHOUSE_CLI
# and CHROME_PATH). Writes <outDir>/lighthouse.json with one summary line per address.

set -uo pipefail

base="${1:?Usage: lighthouse-local.sh <baseUrl> <outDir> <path>...}"
out="${2:?}"
shift 2
lighthouse="${LIGHTHOUSE_CLI:-/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/lighthouse/cli/index.js}"
export CHROME_PATH="${CHROME_PATH:-$(ls -d /home/administrator/.cache/ms-playwright/chromium-*/chrome-linux*/chrome 2>/dev/null | head -1)}"
work="$(mktemp -d)"
trap '[[ -n "$work" && -d "$work" && "$work" == /tmp/* ]] && rm -rf "$work"' EXIT

echo "[" > "$out/lighthouse.json"
first=1
for path in "$@"; do
    node "$lighthouse" "$base$path" --quiet --only-categories=performance,accessibility,best-practices,seo \
        --chrome-flags="--headless=new --no-sandbox" --output=json --output-path="$work/report.json" > /dev/null 2>&1
    line="$(node -e '
        const fs = require("fs");
        const path = process.argv[2];
        if (!fs.existsSync(process.argv[1])) { console.log(JSON.stringify({ path, error: "no report (for example a 404 address)" })); process.exit(0); }
        const r = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
        const a = r.audits;
        const score = (id) => r.categories[id]?.score ?? null;
        const script = (a["resource-summary"]?.details?.items ?? []).find((i) => i.resourceType === "script");
        console.log(JSON.stringify({ path, lighthouse: r.lighthouseVersion, performance: score("performance"), accessibility: score("accessibility"), bestPractices: score("best-practices"), seo: score("seo"),
            fcpMs: Math.round(a["first-contentful-paint"].numericValue), lcpMs: Math.round(a["largest-contentful-paint"].numericValue), cls: Number(a["cumulative-layout-shift"].numericValue.toFixed(4)),
            tbtMs: Math.round(a["total-blocking-time"].numericValue), scriptBytes: script?.transferSize ?? 0,
            notes: ["uses-text-compression", "uses-long-cache-ttl"].filter((id) => a[id] && a[id].score !== null && a[id].score < 1) }));
    ' "$work/report.json" "$path")"
    rm -f "$work/report.json"
    [[ $first -eq 1 ]] || echo "," >> "$out/lighthouse.json"
    echo "$line" >> "$out/lighthouse.json"
    echo "$line"
    first=0
done
echo "]" >> "$out/lighthouse.json"
