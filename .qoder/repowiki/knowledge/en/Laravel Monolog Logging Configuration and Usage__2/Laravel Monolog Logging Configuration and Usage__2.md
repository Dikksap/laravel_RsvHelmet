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
    - app/Console/Commands/SyncExternalBarangCommand.php
---

## What system/approach is used

The application uses Laravel's built-in logging subsystem, which wraps the **Monolog** PHP library. There is no custom logger abstraction or third-party logging framework (e.g., Sentry, Bugsnag) configured — all log output goes through `Illuminate\Support\Facades\Log`.

## Key files and packages

- `config/logging.php` — central configuration defining channels, handlers, levels, and processors.
- `app/Services/ExternalBarangApiService.php` — primary producer of structured logs via `Log::warning()` / `Log::error()` with contextual arrays.
- `app/Services/ExternalBarangSyncService.php` — emits `Log::error()` for sync failures.
- `app/Console/Commands/SyncExternalBarangCommand.php` — uses console-level `$this->info()` / `$this->error()` rather than the Log facade.
- `storage/logs/laravel.log` — default file sink path.

## Architecture and conventions

### Channels

`config/logging.php` defines these channels:

| Channel | Driver | Sink | Level | Notes |
|---|---|---|---|---|
| `stack` | stack | composed from `LOG_STACK` env var (default `'single'`) | per child channel | Aggregator; defaults to single-file sink. |
| `single` | single | `storage_path('logs/laravel.log')` | `env('LOG_LEVEL', 'debug')` | Default development sink. |
| `daily` | daily | same path, rotated by `LOG_DAILY_DAYS` (default 14) | debug | Intended for production rotation. |
| `monthly` | monthly | same path, max 3 files | debug | Alternative rotation strategy. |
| `slack` | slack | webhook URL | critical | Only critical-level alerts reach Slack. |
| `papertrail` | monolog + SyslogUdpHandler | TLS connection to `PAPERTRAIL_URL:PORT` | debug | Uses `PsrLogMessageProcessor`. |
| `stderr` | monolog + StreamHandler | `php://stderr` | debug | Structured via `PsrLogMessageProcessor`. |
| `syslog` | syslog | OS syslog facility | debug | Facility configurable via `LOG_SYSLOG_FACILITY`. |
| `errorlog` | errorlog | PHP error_log | debug | Fallback sink. |
| `null` | monolog + NullHandler | discard | — | Used for deprecations (`LOG_DEPRECATIONS_CHANNEL`). |
| `emergency` | single | `storage_path('logs/laravel.log')` | — | Emergency-only channel. |

### Levels

- The effective level is read from `LOG_LEVEL` (defaults to `debug`).
- Slack channel is pinned to `critical`, so only severe failures trigger notifications.
- Deprecation logging is disabled by default (`LOG_DEPRECATIONS_CHANNEL` → `null`).

### Structured fields

Structured logging is done by passing a second array argument to `Log::warning()` / `Log::error()`. Observed context keys include:

- `external_id` — external item identifier.
- `item_id` — internal item ID.
- `warehouse_id` — warehouse involved in the operation.
- `batch_id` — production batch being reconciled.
- `message` — human-readable description.

Example from `ExternalBarangApiService.php`:
```php
Log::error('ExternalBarangApiService: Error koneksi ke API barang', [
    'external_id' => $externalId,
    'item_id'     => $itemId,
]);
```

The `papertrail` and `stderr` channels register `Monolog\Processor\PsrLogMessageProcessor`, which converts placeholder-style messages into structured JSON suitable for centralized log aggregation.

### Console vs HTTP logging

- Console commands (`SyncExternalBarangCommand`) use Laravel's console helpers (`$this->info()`, `$this->error()`) for user-facing progress and errors — these go to the console, not the file logger.
- Service-layer code uses the `Log` facade for persistent diagnostics.

### Filament UI feedback

Filament pages use notification builders (e.g., `->warning()`, `->info()`) for user-visible messages; these are distinct from server-side log entries and are not routed through the logging subsystem.

## Conventions and constraints

1. **All persistent application logs go through `Illuminate\Support\Facades\Log`** — observed in `ExternalBarangApiService` and `ExternalBarangSyncService`; no direct Monolog usage exists in application code.
2. **Contextual data is passed as an associative array** as the second argument to `Log::warning()` / `Log::error()`, never via string interpolation inside the message body.
3. **Channel selection is environment-driven**: `LOG_CHANNEL` selects the default channel, `LOG_STACK` selects child channels for the stack aggregator, and `LOG_LEVEL` controls verbosity across channels.
4. **Production rotation should use the `daily` channel** (with `LOG_DAILY_DAYS` controlling retention); the `single` channel is the development default and does not rotate.
5. **Only `critical`-level events reach Slack** because the slack channel hardcodes `level => env('LOG_LEVEL', 'critical')`.
6. **Deprecation warnings are suppressed** by default since `LOG_DEPRECATIONS_CHANNEL` defaults to `null`.
7. **Console progress is emitted via console helpers**, not the Log facade, keeping interactive CLI output separate from server-side logs.