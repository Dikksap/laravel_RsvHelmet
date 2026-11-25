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
    - storage/logs/laravel.log
---

## Overview

The application uses Laravel's built-in logging subsystem backed by **Monolog**. There is no custom logger wrapper or dedicated logging package — log calls go directly through the `Illuminate\Support\Facades\Log` facade.

## Configuration (`config/logging.php`)

- Default channel: `stack`, composed from `LOG_STACK` (env, default `single`).
- Deprecations are sent to a `null` channel by default (`LOG_DEPRECATIONS_CHANNEL=null`).
- Available channels defined:
  - `stack` — composes multiple child channels via `LOG_STACK`.
  - `single` → `storage_path('logs/laravel.log')`, level from `LOG_LEVEL` (default `debug`), placeholder replacement enabled.
  - `daily` / `monthly` — same path as single, with rotation (`max_files`).
  - `slack` — webhook-based alerts at `critical` level; username/emoji configurable.
  - `papertrail` — Monolog handler with `SyslogUdpHandler`, PSR message processor.
  - `stderr` — streams to `php://stderr` with optional formatter and PSR processor.
  - `syslog` / `errorlog` — standard OS sinks.
  - `null` — `NullHandler` sink for discarding logs.
  - `emergency` — fallback emergency writer to `storage_path('logs/laravel.log')`.

All channels read their minimum level from `LOG_LEVEL`; Slack defaults to `critical`. Placeholder replacement (`replace_placeholders`) is enabled on every text-based channel.

## Usage in Application Code

Only two service classes use logging, both under `app/Services/`:

- `ExternalBarangApiService` — imports `use Illuminate\Support\Facades\Log;` and emits:
  - `Log::warning(...)` for non-success HTTP responses from the external barang API.
  - `Log::error(...)` for connection failures and per-endpoint errors (`getBarangDetail`, `getRekapVariant`).
- `ExternalBarangSyncService` — emits `Log::error(...)` when recording unit stock fails during sync.

Log messages follow a consistent prefix pattern: `<ClassName>: <human-readable description>` (e.g. `ExternalBarangApiService: Gagal mengambil daftar barang`). Context is passed as a second array argument with structured fields such as `status`, `body`, `error`, `kodeBarang`.

No other files in the codebase call `Log::`, `logger()`, or import the Log facade. Controllers, Models, Filament resources, Livewire components, and most Services do not emit logs directly.

## Output Files

- `storage/logs/laravel.log` — primary file log (from `single`/`daily`/`monthly` channels).
- `storage/logs/browser.log` — present but not configured by `logging.php` (likely produced by another tool or environment).

## Conventions Observed

1. Logging is centralized in `app/Services/*` rather than scattered across controllers or models.
2. Log messages are prefixed with the originating class name for traceability.
3. Structured context arrays are attached to error/warning calls instead of embedding data in the message string.
4. The only levels used in application code are `warning` and `error`; no `info`, `debug`, `notice`, or `critical` calls were found.
5. All log configuration is driven by environment variables (`LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `LOG_SLACK_*`, `LOG_PAPERTRAIL_*`, etc.) with sensible defaults.