---
kind: logging_system
name: Laravel Monolog Logging Configuration and Usage
category: logging_system
scope:
    - '**'
source_files:
    - config/logging.php
    - app/Services/ExternalBarangApiService.php
    - app/Services/ExternalBarangSyncService.php
---

## Overview

The application uses Laravel's built-in logging subsystem, which is backed by **Monolog**. There is no custom logger wrapper or third-party logging library beyond Monolog; all log output goes through `Illuminate\Support\Facades\Log`.

## Configuration (`config/logging.php`)

- Default channel: `stack`, composed from a comma-separated list in `LOG_STACK` (defaulting to `single`).
- Channels defined:
  - `stack` — aggregates one or more child channels via `explode(',', env('LOG_STACK', 'single'))`.
  - `single` — writes to `storage_path('logs/laravel.log')` with level from `LOG_LEVEL` (default `debug`) and `replace_placeholders => true`.
  - `daily` — same path as `single`, rotates after `LOG_DAILY_DAYS` (default 14).
  - `monthly` — rotates monthly, keeps 3 files.
  - `slack` — sends critical-level messages to a Slack webhook (`LOG_SLACK_WEBHOOK_URL`), username from `APP_NAME`, emoji `:boom:`.
  - `papertrail` — Monolog handler using `SyslogUdpHandler`, configured via `PAPERTRAIL_URL`/`PAPERTRAIL_PORT`; includes `PsrLogMessageProcessor`.
  - `stderr` — streams to `php://stderr` via `StreamHandler`, also uses `PsrLogMessageProcessor` and an optional `LOG_STDERR_FORMATTER`.
  - `syslog` — native syslog driver with configurable facility.
  - `errorlog` — PHP error_log driver.
  - `null` — `NullHandler` sink for discarding logs.
  - `emergency` — fallback emergency writer to `storage_path('logs/laravel.log')`.
- Deprecations are routed to a separate `deprecations.channel` (default `null`) with optional trace.

All levels and destinations are driven by environment variables (`LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `LOG_DAILY_DAYS`, `LOG_SLACK_*`, `LOG_PAPERTRAIL_HANDLER`, `LOG_SYSLOG_FACILITY`, `LOG_STDERR_FORMATTER`). No hardcoded sinks exist outside this config file.

## Log Levels Used

Across the codebase, only two levels are used:

- `Log::warning(...)` — non-fatal failures, e.g. external API returning a non-successful HTTP response.
- `Log::error(...)` — exceptions thrown during HTTP calls or database operations.

No `info`, `debug`, `critical`, `alert`, or `notice` calls were found in the application source.

## Structured Fields

Every log call passes a second argument array of key/value context fields. Observed field names include:

- `status`, `body` (HTTP response metadata on failed requests)
- `error` (exception message)
- `kodeBarang` (domain identifier)
- `error` + domain-specific keys in `ExternalBarangSyncService`

There is no shared log-context provider, middleware, or PSR-3 processor that injects request IDs, user IDs, or correlation IDs into every log line; context is attached per-call at the site of the log statement.

## Where Logging Is Used

Logging is concentrated in the integration services that talk to the external Barang API:

- `app/Services/ExternalBarangApiService.php` — wraps HTTP client calls; logs warnings/errors around failed responses and caught exceptions.
- `app/Services/ExternalBarangSyncService.php` — logs errors when recording unit stock fails.

Other service classes (`BatchProgressService`, `BomExplosionService`, `StockMovementService`, etc.) do not emit logs directly.

## Output Files

The default `single` channel writes to `storage/logs/laravel.log`. The repository ships with both `storage/logs/laravel.log` and `storage/logs/browser.log` present, but `browser.log` is not referenced by any config channel and appears to be produced by a browser-side logger rather than the PHP stack.

## Conventions Observed

- External/integration failures are logged with `Log::warning` for recoverable non-2xx responses and `Log::error` for caught exceptions.
- Log messages use a class-prefixed prefix (e.g. `ExternalBarangApiService: ...`) so the origin is visible in flat log files.
- Context is always passed as an associative array alongside the message string.
- No custom formatter or processor is registered beyond the defaults; structured JSON output depends on the downstream sink (e.g. Papertrail) rather than being enforced by the app.
- All logging configuration is centralized in `config/logging.php` and parameterized exclusively through `.env` variables.