---
kind: configuration_system
name: Laravel Configuration System — env-driven config with per-service PHP arrays
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
    - config/mail.php
    - config/services.php
    - config/filesystems.php
    - bootstrap/app.php
---

## Approach

RSV Helmet uses the standard Laravel 13 configuration system: a set of PHP array files under `config/` whose values are resolved from environment variables via `env()`, with `.env.example` as the source-of-truth template for all required and optional variables. There is no custom configuration loader, no YAML/TOML overrides, and no runtime feature-flag service — configuration is purely static at boot time.

## Key Files

- `.env.example` — master list of every environment variable the app expects (APP_*, DB_*, SESSION_*, CACHE_*, REDIS_*, MAIL_*, AWS_*, LOG_*, QUEUE_*, plus the domain-specific `EXTERNAL_BARANG_API_URL`).
- `config/app.php` — application identity, locale (`APP_LOCALE=id`), timezone (`UTC`), encryption key, maintenance mode driver/store.
- `config/database.php` — default connection `sqlite` (overridden by `DB_CONNECTION=mysql` in `.env.example`); connections for sqlite/mysql/mariadb/pgsql/sqlsrv; Redis client `phpredis` with separate `default` and `cache` databases.
- `config/cache.php` — default store `database`; stores include database/file/storage/memcached/redis/dynamodb/octane/failover; cache prefix derived from `APP_NAME`.
- `config/session.php` — default driver `database`; JSON serialization; cookie name derived from `APP_NAME`; SameSite `lax`.
- `config/queue.php` — default connection `database`; failed jobs stored via `database-uuids`; batching table `job_batches`.
- `config/logging.php` — default channel `stack` composed from comma-separated `LOG_STACK`; channels include single/daily/monthly/slack/papertrail/stderr/syslog/errorlog/null/emergency; deprecations go to `null` by default.
- `config/mail.php` — default mailer `log`; transports smtp/ses/postmark/resend/sendmail/log/array/failover/roundrobin; global `from` address/name sourced from `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME=${APP_NAME}`.
- `config/services.php` — third-party credentials (postmark, resend, ses, slack) plus the application-specific `external_barang` service block (`base_url`, `timeout`, `excluded_statuses`, `default_warehouse_id`).
- `config/filesystems.php` — filesystem disks (referenced by `FILESYSTEM_DISK=local` in `.env.example`).
- `bootstrap/app.php` — minimal bootstrap; no custom config loading or provider registration beyond routing and exception JSON rendering.

## Architecture & Conventions

1. **Two-layer config**: each file under `config/` returns a plain PHP array. Values that vary per deployment are read through `env('VAR', <default>)`. The defaults in the PHP files act as documentation of expected types (e.g. `(int) env(...)`).
2. **Environment variables are the only runtime input**: there is no `.env` committed to the repo; `.env.example` documents every variable. Deployment sets the actual values (Docker Compose, production Docker image, CI/CD).
3. **Service-oriented grouping**: application-specific cross-cutting settings live in dedicated config files (`services.php` for external integrations like `external_barang`, `mail.php` for email, etc.) rather than being scattered across `app.php`.
4. **Domain-specific service config**: the `external_barang` entry in `config/services.php` exposes `base_url`, `timeout`, `excluded_statuses`, and `default_warehouse_id` — consumed by `Services/ExternalBarangApiService.php` and `Services/ExternalBarangSyncService.php`.
5. **Default-to-safe values**: most `env()` calls provide sensible defaults so the app boots without an `.env` (e.g. `sqlite` database, `log` mailer, `file` maintenance driver). Production overrides are applied via container/environment.
6. **No feature flags**: toggles such as `APP_DEBUG`, `SESSION_ENCRYPT`, `SESSION_HTTP_ONLY`, `CACHE_PREFIX`, `LOG_LEVEL` serve as operational switches but there is no centralized feature-flag abstraction.
7. **Locale configuration**: `APP_LOCALE=id`, `APP_FALLBACK_LOCALE=en`, `APP_FAKER_LOCALE=en_US` drive the `lang/id/` translation directory used throughout the Filament admin panel.
8. **Secrets pattern**: secrets (DB_PASSWORD, AWS keys, API tokens) are expected exclusively via environment variables; no secrets are embedded in `config/*.php`.

## Conventions & Constraints

- Every configurable value is exposed as an `env()` call in a `config/*.php` file; new settings should follow this pattern rather than reading `$_ENV` directly.
- `.env.example` is the canonical inventory of environment variables — any new `env()` call should be mirrored there with a representative default.
- Database connections are defined per-driver in `config/database.php`; the active one is selected by `DB_CONNECTION` (mysql in `.env.example`, sqlite as framework default).
- Cache, session, queue, and logging backends are selected by their respective `*_DRIVER` / `*_CONNECTION` / `*_STORE` / `*_CHANNEL` environment variables, allowing backend swaps without code changes.
- Third-party service credentials belong in `config/services.php` under named keys (postmark, resend, ses, slack, external_barang), not in `config/app.php`.
- Application-level constants (timezone `UTC`, cipher `AES-256-CBC`, session serialization `json`) are hard-coded in their respective config files and are not exposed as env vars unless explicitly added.