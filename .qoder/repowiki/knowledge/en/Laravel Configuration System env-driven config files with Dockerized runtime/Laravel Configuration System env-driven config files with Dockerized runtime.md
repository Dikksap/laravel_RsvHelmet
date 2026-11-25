---
kind: configuration_system
name: 'Laravel Configuration System: env-driven config files with Dockerized runtime'
category: configuration_system
scope:
    - '**'
source_files:
    - .env.example
    - config/app.php
    - config/database.php
    - config/services.php
    - config/logging.php
    - config/mail.php
    - config/cache.php
    - config/session.php
    - config/queue.php
    - config/filesystems.php
    - docker/production/php.ini
    - docker/production/supervisord.conf
---

## Approach

The application uses the standard Laravel configuration system. All runtime settings are loaded from PHP array files under `config/`, and every value is resolved via `env('KEY', default)` — meaning `.env` (or environment variables injected by the container) is the single source of truth for per-environment overrides.

There is no custom configuration loader, no YAML/TOML config, no feature-flag service, and no secrets manager integration beyond plain environment variables.

## Key Files

- `config/app.php` — app name, locale (`id`), fallback locale (`en`), encryption key/cipher, maintenance mode driver/store.
- `config/database.php` — connections for sqlite/mysql/mariadb/pgsql/sqlsrv; Redis `default` and `cache` clusters with backoff/retry options.
- `config/services.php` — third-party credentials (postmark, resend, ses, slack) plus a project-specific `external_barang` section (`base_url`, `timeout`, `excluded_statuses`, `default_warehouse_id`).
- `config/logging.php` — Monolog channels (`stack`, `single`, `daily`, `monthly`, `slack`, `papertrail`, `stderr`, `syslog`, `errorlog`, `null`, `emergency`); default channel is `stack` composed from `LOG_STACK`.
- `config/mail.php` — mailer transports (smtp/ses/postmark/resend/sendmail/log/array/failover/roundrobin); default mailer is `log`; global `from` address/name sourced from `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`.
- `config/cache.php`, `config/session.php`, `config/queue.php`, `config/filesystems.php`, `config/auth.php` — framework defaults, all env-backed.
- `.env.example` — the canonical list of required/optional environment variables (DB, Redis, Mail, AWS, Vite, `EXTERNAL_BARANG_API_URL`).
- `docker/production/php.ini` — hard PHP runtime settings (memory_limit=512M, upload_max_filesize=64M, date.timezone=Asia/Jakarta, expose_php=Off, opcache enabled).
- `docker/production/supervisord.conf` — runs php-fpm, nginx, and a queue worker (`php artisan queue:work --sleep=3 --tries=3 --max-time=3600`) as separate processes.

## Architecture & Conventions

1. **Config files are pure PHP arrays** — no dynamic loading, no classes, no schema validation. Each file returns an associative array whose keys map directly to `config()` accessors.
2. **Every mutable setting goes through `env()`** — even values that never change in this repo (e.g. `'timezone' => 'UTC'` in `app.php` is not env-backed). The convention is to put environment-sensitive values behind `env('VAR', default)` so they can be overridden without touching code.
3. **Environment variable naming follows Laravel conventions**: `APP_*`, `DB_*`, `REDIS_*`, `CACHE_*`, `SESSION_*`, `QUEUE_*`, `LOG_*`, `MAIL_*`, `AWS_*`, plus one project-specific prefix `EXTERNAL_BARANG_*`.
4. **Third-party integrations live in `config/services.php`** — external API credentials are grouped under named keys (`postmark`, `resend`, `ses`, `slack`, `external_barang`). The `external_barang` entry is the only application-specific service block and is consumed by `Services/ExternalBarangApiService.php` / `ExternalBarangSyncService.php`.
5. **No feature flags or runtime toggles** — behavior differences between environments are driven purely by `APP_ENV` + corresponding env vars (e.g. `APP_DEBUG`, `LOG_LEVEL`, `MAIL_MAILER=log`).
6. **Secrets are plain environment variables** — there is no .env.production file committed, no encrypted secrets store, no vault integration. Secrets (DB password, AWS keys, APP_KEY) are expected to be provided by the deployment platform/container runtime.
7. **PHP-level runtime tuning is separate from Laravel config** — timezone, memory limits, upload sizes, and opcache are set in `docker/production/php.ini`, not in `config/app.php`.
8. **Process/runtime orchestration is handled by supervisord** — the queue worker is started as a supervised process with fixed retry/backoff parameters baked into the command line rather than read from config.

## Conventions & Constraints

- **All environment variables are documented in `.env.example`** — adding a new env var should also update this file so developers know what to configure.
- **Database connections are defined for multiple drivers** (sqlite, mysql, mariadb, pgsql, sqlsrv) but only `mysql` is active in `.env.example`; switching databases means changing `DB_CONNECTION` and providing the matching host/port/credentials.
- **Redis is configured with two logical connections** (`default` and `cache`) sharing the same host/password but different databases; cache uses `REDIS_CACHE_DB` while the default uses `REDIS_DB`.
- **Logging defaults to `LOG_CHANNEL=stack` with `LOG_STACK=single`**, writing to `storage/logs/laravel.log` at `debug` level — suitable for local development; production would typically switch to `daily` or `stderr`.
- **Mail defaults to the `log` transport** (`MAIL_MAILER=log`) — emails are written to the log instead of being sent, which is safe for development.
- **The `external_barang` service URL defaults to `http://host.docker.internal:8000`** — this is a Docker-hosted dependency and must be adjusted when running outside Docker.
- **Session driver is `database`** and cache driver is `database` by default in `.env.example`, meaning both require the `sessions` and `cache` tables to exist (created by migrations).
- **Timezone is set twice**: `date.timezone = Asia/Jakarta` in `php.ini` and `APP_LOCALE=id` in `config/app.php` — the former affects PHP's date functions globally, the latter affects Laravel's locale-aware formatting.