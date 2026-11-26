---
kind: configuration_system
name: 'Laravel Configuration System: .env + config/ Files with Docker-Driven Environment'
category: configuration_system
scope:
    - '**'
source_files:
    - .env.example
    - bootstrap/providers.php
    - app/Providers/AppServiceProvider.php
    - config/app.php
    - config/database.php
    - config/logging.php
    - config/mail.php
    - config/filesystems.php
    - config/services.php
    - docker-compose.yml
    - Dockerfile.prod
    - docker/production/php.ini
---

## Overview

This repository uses the standard Laravel configuration system. Runtime configuration is split across two layers:

1. **Environment variables** — defined in `.env.example` (the source of truth for required keys) and loaded at runtime via `Illuminate\Support\Env`.
2. **PHP config files** — under `config/`, each returning an associative array whose values are resolved through `env()` calls with sensible defaults.

There is no custom configuration loader, no YAML/TOML overrides, no feature-flag service, and no secrets manager integration beyond plain environment variables.

## Key Files

- `.env.example` — documents every supported environment variable (69 lines), including app, DB, session, cache, Redis, mail, AWS, Vite, and a domain-specific `EXTERNAL_BARANG_API_URL`.
- `bootstrap/providers.php` — registers only `AppServiceProvider` and `App\Providers\Filament\AdminPanelProvider`; no application-level bootstrapping of configuration occurs here.
- `app/Providers/AppServiceProvider.php` — empty `register()` / `boot()`, so no programmatic config merging or overrides.
- `config/app.php` — core app settings (`name`, `env`, `debug`, `url`, `locale`, `fallback_locale`, `faker_locale`, `cipher`, `key`, `previous_keys`, `maintenance`).
- `config/database.php` — connections for `sqlite`, `mysql`, `mariadb`, `pgsql`, `sqlsrv`, plus a `redis` section with `default` and `cache` sub-connections.
- `config/logging.php` — Monolog-based channels (`stack`, `single`, `daily`, `monthly`, `slack`, `papertrail`, `stderr`, `syslog`, `errorlog`, `null`, `emergency`).
- `config/mail.php` — mailers (`smtp`, `ses`, `postmark`, `resend`, `sendmail`, `log`, `array`, `failover`, `roundrobin`) with global `from` address.
- `config/filesystems.php` — disks `local`, `public`, `s3` with `links` mapping `public_path('storage')` → `storage_path('app/public')`.
- `config/services.php` — third-party credentials (`postmark`, `resend`, `ses`, `slack`) plus a project-specific `external_barang` service block (`base_url`, `timeout`, `excluded_statuses`, `default_warehouse_id`).
- `docker-compose.yml` — injects DB credentials into the MariaDB container using `${DB_*}` env vars with a hard failure on missing `DB_PASSWORD` (`${DB_PASSWORD:?DB_PASSWORD harus diset di .env}`).
- `Dockerfile.prod` — production image that copies `docker/production/php.ini`, `nginx.conf`, `supervisord.conf`, and `entrypoint.sh` into the runtime container.
- `docker/production/php.ini` — sets `memory_limit=512M`, `max_execution_time=300`, `upload_max_filesize=64M`, `post_max_size=64M`, `date.timezone=Asia/Jakarta`, `expose_php=Off`, and enables opcache.

## Architecture & Conventions

### Layering pattern
Every value in `config/*.php` is read via `env('KEY', default)`. The file provides the schema and defaults; `.env` provides per-deployment overrides. There is no runtime merging layer — this is pure Laravel convention.

### Domain-scoped config groupings
- Application identity and locale live in `config/app.php`.
- Infrastructure services (database, redis, filesystem, mail, logging) live in their own `config/` files.
- Third-party integrations live in `config/services.php`.
- Application-specific external API wiring lives as a custom key `external_barang` inside `config/services.php` (not a separate file).

### Environment variable naming
Variables follow Laravel's conventional prefixes:
- `APP_*` for application metadata
- `DB_*` for database connectivity
- `REDIS_*` for caching/queue backend
- `MAIL_*` for email transport
- `AWS_*` for S3 storage
- `LOG_*` for logging channel selection
- `SESSION_*` for session driver/lifetime
- `VITE_*` for frontend asset build-time injection
- `EXTERNAL_BARANG_*` for the external barang synchronization API

### Defaults strategy
Defaults are baked into the `config/*.php` files rather than relying on `.env.example` alone. For example, `config/app.php` defaults `locale` to `'id'`, `timezone` to `'UTC'`, and `maintenance.driver` to `'file'`; `config/database.php` defaults `default` to `'sqlite'`; `config/logging.php` defaults `default` to `'stack'` with stack children `['single']`; `config/mail.php` defaults `default` to `'log'`; `config/filesystems.php` defaults `default` to `'local'`.

### Production vs development separation
Development runs via `docker-compose.yml` which mounts the source directory and exposes port `8001:8000`. Production builds a multi-stage image (`Dockerfile.prod`) that installs PHP extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`), compiles assets via Vite, and runs Nginx + Supervisor + PHP-FPM. PHP runtime tuning is centralized in `docker/production/php.ini`.

### No custom configuration loading
`AppServiceProvider::register()` and `AppServiceProvider::boot()` are both empty. All configuration is declarative in `config/` files. There is no programmatic override, no config factory, no feature flag registry, and no secret rotation logic.

## Conventions & Constraints

- **All runtime configuration goes through `env()` in `config/*.php`** — observed consistently across `app.php`, `database.php`, `logging.php`, `mail.php`, `filesystems.php`, and `services.php`. No direct `$_ENV` access was found in these files.
- **`.env.example` is the contract for required environment variables** — it documents every supported key with its default value, serving as the deployment checklist.
- **Database credentials are mandatory in Docker Compose** — `docker-compose.yml` enforces `DB_PASSWORD` via the shell error syntax `${DB_PASSWORD:?DB_PASSWORD harus diset di .env}`, causing container startup to fail if unset.
- **Production timezone is fixed to `Asia/Jakarta`** — enforced by `docker/production/php.ini` (`date.timezone = Asia/Jakarta`), overriding Laravel's default `UTC` from `config/app.php`.
- **External API configuration is grouped under `config('services.external_barang')`** — the `external_barang` key in `config/services.php` centralizes `base_url`, `timeout`, `excluded_statuses`, and `default_warehouse_id` for the external barang sync feature.
- **No feature flags or toggles** — there is no boolean flag system, no `Feature` class, and no runtime toggle mechanism beyond environment variables.
- **No secrets manager** — secrets (DB passwords, AWS keys, API tokens) are stored as plain environment variables; there is no Vault, AWS Secrets Manager, or encrypted `.env` usage.