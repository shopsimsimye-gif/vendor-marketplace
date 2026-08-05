# Architecture Test — Vendor Marketplace

Static-analysis guard (no WordPress required) that enforces the centralized
AJAX/REST architecture so the codebase cannot silently drift again.

## What it enforces

| # | Rule | Default |
|---|------|---------|
| 1 | No direct `add_action('wp_ajax_*')` outside the whitelist (`CoreServiceProvider`, `CronServiceProvider`, `VendorRequestsAdminPage`). Registrations inside known-dead code → WARN only. | FAIL |
| 2 | No duplicate AJAX registration within RouteRegistry. | FAIL |
| 3 | Every action referenced by the UI must have a live handler. | FAIL |
| 4 | Registered handlers with no UI reference (diagnostic breath). | NOTE (WARN under `--strict`) |
| 5 | RouteRegistry entries reference existing controller & request classes. | FAIL |
| 6 | REST routes registered only from the whitelisted sources; no live duplicate. | FAIL |
| 7 | Legacy entry points (`registerAjaxHandlers`/`registerRestRoutes`/`enqueueAssets`) must not be live-called. | FAIL |

Exit code: `0` pass, `1` failures, `2` runtime error.

## Usage

```sh
# auto-detect plugin root (from scripts/ location)
php scripts/architecture-test.php

# explicit root
php scripts/architecture-test.php --root=/var/www/html/wp-content/plugins/vendor-marketplace

# treat "no UI reference" as failures too (strict mode)
php scripts/architecture-test.php --strict

# shell wrapper
scripts/run-architecture-test.sh
```

## CI

GitHub Actions: `.github/workflows/architecture.yml` runs the test on every
push and PR. It uses the default (non-strict) mode so that only true
architectural violations (the 7 broken AJAX actions, duplicates, REST
mis-registration) block the pipeline — orphan handlers stay as diagnostics.

## Current baseline (2026-08-05)

- RouteRegistry AJAX actions : 60
- live REST routes           : 10
- UI-referenced actions      : 38
- Known EXPECTED failures    : 10
  (7 interface/handler mismatches + 3 legacy multi-step actions — fixed in
  Phase 2; the guard goes green once resolved.)
- Direct AJAX whitelist hooks: 2 (documented exceptions:
  `vmp_run_queue` cron, `vmp_vendor_requests_action` admin page)

## Maintenance

If a new genuine exception is needed (allowed whitelist file, live REST
source, dead module, or legacy-dead method), update the `const` blocks at the
top of `architecture-test.php` with a comment explaining why. A guard that is
silently bypassed is worse than none — keep the whitelist minimal and audited.
