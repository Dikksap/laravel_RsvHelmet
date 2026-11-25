---
kind: configuration_system
name: Laravel Configuration System via .env and config/*.php
category: configuration_system
scope:
    - '**'
source_files:
    - .env.example
    - config/app.php
    - config/database.php
    - config/cache.php
    - config/session.php
    - config/queue.php
    - config/logging.php
    - config/filesystems.php
    - config/mail.php
    - config/services.php
    - bootstrap/providers.php
    - app/Providers/AppServiceProvider.php
---

## Overview

This is a standard Laravel 13 application. The configuration system follows the framework's built-in pattern: runtime values are loaded from `.env` (or environment variables) into PHP arrays defined under `config/`, with no custom configuration loader, feature-flag service, or secrets manager beyond what Laravel provides.

## Key Files and Packages

- **`.env.example`** — template of all supported environment variables (69 keys covering app, DB, cache, session, queue, mail, AWS, Redis, logging, and the project-specific `EXTERNAL_BARANG_*` variables).
- **`bootstrap/providers.php`** — registers only two providers (`AppServiceProvider`, `AdminPanelProvider`); no custom config loading happens here.
- **`app/Providers/AppServiceProvider.php`** — empty; no boot-time config overrides.
- **`config/app.php`** — core app settings (`name`, `env`, `debug`, `url`, `locale`, `fallback_locale`, `faker_locale`, `key`, `previous_keys`, maintenance driver/store), all read via `env()`.
- **`config/database.php`** — connections for `sqlite`, `mysql`, `mariadb`, `pgsql`, `sqlsrv`; Redis client and two named connections (`default`, `cache`) with retry/backoff options.
- **`config/cache.php`** — default store `database`; stores include array, database, file, storage, memcached, redis, dynamodb, octane, failover; key prefix uses `Str::slug(APP_NAME)`.
- **`config/session.php`** — driver `database`, JSON serialization, cookie name derived from `APP_NAME`, `same_site=lax`, `http_only=true`.
- **`config/queue.php`** — default connection `database`; supports sync, database, beanstalkd, sqs, redis, deferred, background, failover; failed jobs use `database-uuids`.
- **`config/logging.php`** — default channel `stack` composed from `LOG_STACK=single`; channels include single/daily/monthly/slack/papertrail/stderr/syslog/errorlog/null/emergency; deprecations go to `null` by default.
- **`config/filesystems.php`** — disks `local` (`storage_path('app/private')`), `public` (`storage_path('app/public')`), `s3`; symlink `public/storage -> storage/app/public`.
- **`config/mail.php`** — default mailer `log`; transports smtp/postmark/resend/sendmail/log/array/failover/roundrobin; global `from` address/name sourced from `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.
- **`config/services.php`** — third-party credentials for postmark, resend, ses, slack, plus a project-specific `external_barang` section (`base_url`, `timeout`, `excluded_statuses`, `default_warehouse_id`).
- **`lang/id/*.php`** — localized strings for auth, pagination, passwords, validation (i18n configuration lives in `config/app.php` locale fields).

## Architecture and Conventions

1. **Single source of truth is `.env`**. Every runtime value is pulled through Laravel's `env()` helper inside `config/*.php` files. There is no custom env loader, no YAML/TOML config, no runtime config API.
2. **Environment variables follow Laravel conventions**: `APP_*`, `DB_*`, `CACHE_*`, `SESSION_*`, `QUEUE_*`, `REDIS_*`, `MEMCACHED_*`, `AWS_*`, `MAIL_*`, `LOG_*`, plus project-specific `EXTERNAL_BARANG_*` variables.
3. **Defaults are always provided as the second argument to `env()`**, so the app can start without an `.env` file (e.g., `env('DB_CONNECTION', 'sqlite')`).
4. **Type coercion is explicit where needed**: `(bool) env('APP_DEBUG', false)`, `(int) env('SESSION_LIFETIME', 120)`, `(int) env('EXTERNAL_BARANG_API_TIMEOUT', 15)`.
5. **Secrets are not encrypted or vaulted** — they live as plain-text environment variables (see `.env.example` with placeholder values like `changeme`, `rootpassword`). No `.env.production` or secret rotation mechanism is present.
6. **No feature flags or toggle system** — behavior is controlled purely by environment variables and static config arrays.
7. **Third-party integrations are centralized in `config/services.php`** (postmark, resend, ses, slack, external_barang). Application code reads them via `config('services.external_barang.base_url')` rather than calling `env()` directly.
8. **Vite exposure**: `VITE_APP_NAME="${APP_NAME}"` in `.env.example` exposes the app name to the frontend build.
9. **Dockerized defaults**: `.env.example` targets Docker Compose (`DB_HOST=db`, `EXTERNAL_BARANG_API_URL=http://host.docker.internal:8000`, `REDIS_HOST=127.0.0.1`).

## Conventions and Constraints

- All configuration values are accessed through `env()` inside `config/*.php` files; there is no custom configuration class or facade.
- The application ships with sensible defaults for every `env()` call, making it runnable out-of-the-box with SQLite (`config/database.php` default driver is `sqlite`).
- Database connections are declared per-driver blocks (`sqlite`, `mysql`, `mariadb`, `pgsql`, `sqlsrv`) sharing a common structure; only one is active at runtime based on `DB_CONNECTION`.
- Redis is configured twice: once under `database.redis` (for queues/jobs) and once referenced by cache/session via `REDIS_*` variables; both share host/port/password but use different databases (`0` vs `1`).
- Cache key prefixing is automatic: `Str::slug(APP_NAME) . '-cache-'` and `Str::slug(APP_NAME) . '-database-'` for Redis.
- Session serialization is locked to `'json'` (not `'php'`) to avoid gadget-chain attacks if `APP_KEY` leaks — this is a hard-coded invariant in `config/session.php`.
- Cache serializable classes are disabled (`serializable_classes => false`) for the same security reason.
- Filesystem disk `public` derives its URL from `APP_URL . '/storage'`; the `storage:link` symlink target is hard-coded as `public/storage => storage_path('app/public')`.
- Mail default transport is `log` (development-friendly); production would need `smtp`, `ses`, `postmark`, or `resend` set via `MAIL_MAILER`.
- Logging stack composes multiple channels from a comma-separated `LOG_STACK` env var (default `single`); deprecation logs are silenced by default (`LOG_DEPRECATIONS_CHANNEL=null`).
- Queue default backend is `database` with a `jobs` table; failed jobs are stored via `database-uuids` in a `failed_jobs` table.
- The `external_barang` service config groups related settings (`base_url`, `timeout`, `excluded_statuses`, `default_warehouse_id`) under a single namespace in `config/services.php`, which is the convention used to access these values from services.