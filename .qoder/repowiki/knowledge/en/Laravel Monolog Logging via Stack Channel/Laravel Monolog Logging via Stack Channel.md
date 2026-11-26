---
kind: logging_system
name: Laravel Monolog Logging via Stack Channel
category: logging_system
scope:
    - '**'
source_files:
    - config/logging.php
    - .env.example
    - app/Services/ExternalBarangApiService.php
    - app/Services/ExternalBarangSyncService.php
---

## Overview

The application uses Laravel's built-in logging system backed by **Monolog**. There is no custom logger wrapper or PSR-3 facade layer — code calls `Log::` (the Laravel `Illuminate\Support\Facades\Log`) directly from service classes.

## Configuration

- **Config file:** `config/logging.php`
- **Default channel:** `stack` (configured via `LOG_CHANNEL=stack` in `.env.example`).
- **Stack composition:** `LOG_STACK=single` — the stack currently contains only the `single` channel, so all logs go to one file.
- **Level:** `LOG_LEVEL=debug` (`.env.example`).
- **Deprecations:** `LOG_DEPRECATIONS_CHANNEL=null` — deprecation warnings are discarded.

Available channels defined in `config/logging.php`:

| Channel | Driver / Handler | Destination |
|---|---|---|
| `stack` | `stack` (aggregator) | Composed of channels from `LOG_STACK` |
| `single` | `single` | `storage_path('logs/laravel.log')` |
| `daily` | `daily` | Same path as `single`, rotated with `LOG_DAILY_DAYS` (default 14) |
| `monthly` | `monthly` | Same path, max 3 files |
| `slack` | `slack` | Webhook at `LOG_SLACK_WEBHOOK_URL`, level `critical` |
| `papertrail` | Monolog `SyslogUdpHandler` | TLS connection to `PAPERTRAIL_URL:PORT` |
| `stderr` | Monolog `StreamHandler` on `php://stderr` |
| `syslog` | `syslog` facility `LOG_USER` |
| `errorlog` | PHP `errorlog` |
| `null` | Monolog `NullHandler` |
| `emergency` | File fallback (no driver key) |

All file-based channels set `replace_placeholders => true`, enabling Monolog's `%context%` / `%extra%` interpolation.

## Usage Pattern

Logging is used sparingly and only in the external-barang integration services:

- `app/Services/ExternalBarangApiService.php` — logs `warning` when fetching the barang list fails, and `error` for connection failures, `getBarangDetail` errors, and `getRekapVariant` errors. Each call passes a message string plus an associative array of context fields.
- `app/Services/ExternalBarangSyncService.php` — logs `error` when recording unit stock fails.

Example shape observed:
```php
Log::error('ExternalBarangApiService: Error koneksi ke API barang', [
    // contextual fields
]);
```

No structured-field conventions (e.g. `user_id`, `item_id`) are enforced by a shared logger helper; each call hand-writes its own context array.

## Sinks and Rotation

- The active sink is `storage/logs/laravel.log` (file).
- No dedicated error-only channel is configured; `error` and `warning` messages share the same file as debug output.
- Rotation is available via the `daily` / `monthly` channels but not wired into the default stack.
- Slack alerts are pre-configured for `critical` level but require `LOG_SLACK_WEBHOOK_URL` to be set.

## Conventions Observed

- Log messages are prefixed with the service class name (e.g. `ExternalBarangApiService:`) to identify the source without relying on Monolog's processor.
- Contextual data is passed as the second argument to `Log::level()` calls rather than using `withContext()`.
- No custom Monolog processors, formatters, or channels are registered in `AppServiceProvider`; the defaults from `config/logging.php` are used as-is.
- Deprecation logs are intentionally silenced (`null` channel).

## Rules Enforced by Configuration

- The default log channel is always read from `LOG_CHANNEL` (defaults to `stack`).
- The stack's child channels are split from `LOG_STACK` by comma, so adding more sinks requires updating that env var.
- Deprecation logging is disabled unless `LOG_DEPRECATIONS_CHANNEL` is changed from `null`.