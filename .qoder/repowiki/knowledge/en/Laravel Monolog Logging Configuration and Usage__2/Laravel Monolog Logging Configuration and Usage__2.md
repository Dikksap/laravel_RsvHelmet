---
kind: logging_system
name: Laravel Monolog Logging Configuration and Usage
category: logging_system
scope:
    - '**'
source_files:
    - config/logging.php
    - .env.example
    - app/Services/ExternalBarangApiService.php
    - app/Services/ExternalBarangSyncService.php
---

## What System/Approach Is Used

The application uses Laravel's built-in logging subsystem backed by **Monolog**. There is no custom logger wrapper or third-party logging framework — all log output goes through the `Illuminate\Support\Facades\Log` facade (aliased as `Log`). The configuration lives in `config/logging.php`, which defines a stack of channels that route messages to file, stderr, syslog, Slack, Papertrail, or null sinks.

## Key Files and Packages

- `config/logging.php` — central channel definitions (stack, single, daily, monthly, slack, papertrail, stderr, syslog, errorlog, null, emergency).
- `.env.example` — default logging environment variables: `LOG_CHANNEL=stack`, `LOG_STACK=single`, `LOG_DEPRECATIONS_CHANNEL=null`, `LOG_LEVEL=debug`.
- `storage/logs/laravel.log` — default file sink path (`storage_path('logs/laravel.log')`).
- `app/Services/ExternalBarangApiService.php` — primary consumer of `Log::warning()` / `Log::error()` with contextual arrays.
- `app/Services/ExternalBarangSyncService.php` — secondary consumer using `Log::error()`.

## Architecture and Conventions

**Channel strategy:**
- The default channel is `stack` (from `env('LOG_CHANNEL', 'stack')`), which fans out to one or more child channels listed in `LOG_STACK` (default `'single'`).
- The `single` channel writes to `storage/logs/laravel.log` at `debug` level with placeholder replacement enabled.
- A `daily` channel is preconfigured with `max_files => env('LOG_DAILY_DAYS', 14)` for log rotation.
- A `slack` channel exists but is not wired into the default stack; it targets `critical` level and reads webhook URL from `LOG_SLACK_WEBHOOK_URL`.
- A `papertrail` channel uses Monolog's `SyslogUdpHandler` with `PsrLogMessageProcessor` for structured message interpolation.
- A `stderr` channel writes via `StreamHandler` to `php://stderr`, also with `PsrLogMessageProcessor`.
- Deprecation logs are disabled by default (`LOG_DEPRECATIONS_CHANNEL=null`).

**Usage pattern in code:**
- Log calls are made exclusively via the `Log` facade (`use Illuminate\Support\Facades\Log;`) inside service classes under `app/Services/`.
- Messages use string templates plus an associative context array, e.g.:
  ```php
  Log::warning('ExternalBarangApiService: Gagal mengambil daftar barang', [
      // contextual fields
  ]);
  ```
- Only `warning` and `error` levels are used in the codebase; no `info`, `debug`, `notice`, `alert`, or `emergency` calls were found.
- Context arrays carry domain-specific identifiers (e.g., item codes, API response fragments) alongside human-readable messages.

**Environment-driven behavior:**
- `APP_DEBUG=true` in `.env.example` means debug-level logs are emitted locally.
- `LOG_LEVEL=debug` sets the minimum threshold for all channels.
- `LOG_STACK=single` routes the stack to the single-file channel by default.
- Production deployments can switch `LOG_CHANNEL` to `daily`, `stderr`, `syslog`, or a multi-channel stack without code changes.

## Conventions and Constraints

- **All log output flows through `Illuminate\Support\Facades\Log`** — no direct Monolog instantiation was observed in application code.
- **Structured context is passed as associative arrays** to every `Log::warning()` / `Log::error()` call, enabling field-based filtering when downstream processors (like `PsrLogMessageProcessor`) are active.
- **Placeholder replacement is enabled** on file-based channels (`replace_placeholders => true`), so `{foo}` tokens in messages are interpolated from context arrays.
- **Deprecation logging is intentionally off** in the default environment (`LOG_DEPRECATIONS_CHANNEL=null`), preventing noisy deprecation warnings from polluting logs during development.
- **No custom formatter or processor is registered** in this project — only the stock Monolog handlers and the PSR message processor are used.
- **Log files are written to `storage/logs/`**, which is gitignored (`storage/logs/.gitignore` present), ensuring logs never enter version control.