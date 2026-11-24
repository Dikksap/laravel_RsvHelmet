---
kind: logging_system
name: Laravel Monolog Logging Stack with Structured Error Context
category: logging_system
scope:
    - '**'
source_files:
    - config/logging.php
    - app/Services/ExternalBarangApiService.php
    - app/Services/ExternalBarangSyncService.php
---

## Overview

The RSV Helmet application uses Laravel's built-in logging system backed by **Monolog**. There is no custom logger wrapper or dedicated logging package — all log calls go through `Illuminate\Support\Facades\Log` (or the `logger()` helper), and channel/formatter behavior is configured in `config/logging.php`.

## Configuration (`config/logging.php`)

- Default channel: `stack`, composed from a comma-separated list read from `LOG_STACK` (defaulting to `single`).
- Deprecations are sent to a separate `deprecations` channel, defaulting to `null` (no deprecation logs).
- Available channels include:
  - `single` — file sink at `storage_path('logs/laravel.log')`, level from `LOG_LEVEL` (default `debug`), `replace_placeholders => true`.
  - `daily` / `monthly` — rotated file sinks with `max_files` support.
  - `slack` — webhook-based alerts, default level `critical`, username/emoji configurable via env.
  - `papertrail` — Monolog handler using `SyslogUdpHandler`, with `PsrLogMessageProcessor` for structured PSR-3 message interpolation.
  - `stderr` — writes to `php://stderr` via `StreamHandler`, also with `PsrLogMessageProcessor`.
  - `syslog` / `errorlog` — OS-level sinks.
  - `null` — drops all messages (useful for tests).
  - `emergency` — fallback path to `storage_path('logs/laravel.log')`.
- All channels except `null` use `env('LOG_LEVEL', 'debug')` as their minimum level.
- Placeholder replacement is enabled on most channels via `replace_placeholders => true`.

## Usage Pattern

Logging is used sparingly and only in the external-barang integration services:

- `app/Services/ExternalBarangApiService.php` — logs API failures:
  - `Log::warning(...)` when an HTTP response is unsuccessful (includes `status` and `body`).
  - `Log::error(...)` when exceptions occur during API calls (includes `error` message; one call also includes `kodeBarang`).
- `app/Services/ExternalBarangSyncService.php` — logs per-unit database write failures inside the `DB::transaction` catch block (includes `kodeBarang` and `error`).

All log entries follow the same shape: a human-readable Indonesian message string plus an associative array of contextual fields. No structured JSON formatter is configured in `logging.php`; the default Monolog text format is used.

## Conventions Observed

1. **Channel selection is environment-driven**: `LOG_CHANNEL` selects the default channel; `LOG_STACK` composes multiple channels via the `stack` driver.
2. **Levels are uniform across channels**: every channel reads its threshold from `LOG_LEVEL`, so changing the environment variable relevels all sinks simultaneously.
3. **Contextual data is passed as the second argument** to `Log::warning` / `Log::error`, not interpolated into the message string — this keeps messages stable while attaching structured fields like `status`, `body`, `kodeBarang`, and `error`.
4. **Only error/warning levels are used in application code**; there are no `Log::info`, `Log::debug`, `Log::notice`, `Log::alert`, or `Log::emergency` calls in the application layer.
5. **No custom logger class or facade alias exists** — callers import `Illuminate\Support\Facades\Log` directly.
6. **Deprecation logging is disabled by default** (`LOG_DEPRECATIONS_CHANNEL=null`), meaning PHP deprecation notices do not pollute the main log stream unless explicitly enabled.
7. **PSR-3 processor usage is limited to the `papertrail` and `stderr` channels**, where `PsrLogMessageProcessor` is registered to interpolate `{message}` placeholders before sending to those handlers.