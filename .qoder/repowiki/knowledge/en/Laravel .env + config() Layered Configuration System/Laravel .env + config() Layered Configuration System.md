---
kind: configuration_system
name: Laravel .env + config() Layered Configuration System
category: configuration_system
scope:
    - '**'
source_files:
    - .env.example
    - config/app.php
    - config/services.php
    - config/database.php
    - config/cache.php
    - config/session.php
    - config/logging.php
    - config/mail.php
    - config/queue.php
    - config/filesystems.php
    - config/auth.php
    - app/Services/ExternalBarangApiService.php
---

## Approach

This is a standard Laravel application that uses Laravel's built-in configuration system: PHP array files under `config/` provide defaults, while `.env` (and `.env.example`) supplies environment-specific overrides via the `env()` helper. There is no custom configuration loader, YAML/TOML parser, feature-flag library, or secrets manager — all runtime values flow through Laravel's `Illuminate\Config\Repository`.

## Key Files

- `.env.example` — template of every environment variable the app expects; this is the single source of truth for required env vars.
- `config/app.php` — core app settings (`name`, `env`, `debug`, `url`, `locale`, `timezone`, `cipher`, `key`, `previous_keys`, `maintenance`).
- `config/services.php` — third-party service credentials plus the project's own `external_barang` service block.
- `config/database.php`, `config/cache.php`, `config/session.php`, `config/logging.php`, `config/mail.php`, `config/queue.php`, `config/filesystems.php`, `config/auth.php` — framework subsystems, each reading from matching `DB_*`, `CACHE_*`, `SESSION_*`, `LOG_*`, `MAIL_*`, `QUEUE_*`, `REDIS_*`, `AWS_*`, `AUTH_*` env keys.
- `app/Services/ExternalBarangApiService.php` — the only application code that reads config at runtime; it consumes `services.external_barang.*`.

## Architecture and Conventions

1. **Two-layer model** — `config/*.php` declares the shape and defaults; `.env` fills in per-environment values. No layering beyond that (no per-environment config directories like `config/local.php`).

2. **Environment variables are declared centrally** — `.env.example` lists every expected key with sensible defaults. New env vars should be added there first so they appear in `.env` on new installs.

3. **Application-level config lives in `config/app.php`** using `env('KEY', 'default')`. The app uses:
   - `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
   - `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`
   - `APP_KEY`, `APP_PREVIOUS_KEYS`
   - `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE`

4. **Third-party / external integrations live in `config/services.php`** — the convention is to group related env vars into a named service array. The project adds its own `external_barang` service:
   ```php
   'external_barang' => [
       'base_url'        => env('EXTERNAL_BARANG_API_URL', 'http://host.docker.internal:8000'),
       'timeout'         => (int) env('EXTERNAL_BARANG_API_TIMEOUT', 15),
       'excluded_statuses' => ['REGISTER'],
       'default_warehouse_id' => env('EXTERNAL_BARANG_DEFAULT_WAREHOUSE_ID'),
   ],
   ```
   This is consumed as `config('services.external_barang.base_url')` inside `ExternalBarangApiService::__construct`.

5. **Runtime access pattern** — services read config via `config('services.external_barang.*')` rather than injecting it, allowing constructor argument overrides for testing (see `ExternalBarangApiService` accepts optional `$baseUrl` and `$timeout`).

6. **No feature flags** — toggles are expressed as env vars (e.g. `APP_DEBUG`, `SESSION_ENCRYPT`, `CACHE_STORE`) or as static arrays in config (e.g. `excluded_statuses`). There is no feature-flag package or flag table.

7. **Secrets** — sensitive values (`APP_KEY`, `DB_PASSWORD`, AWS keys, mail passwords) are expected to come from `.env` and are never committed. `.gitignore` excludes `.env`; `.env.example` contains placeholders only.

8. **Frontend exposure** — only `VITE_APP_NAME` is forwarded from PHP env to the JS build via `vite.config.js`/`.env` interpolation; other secrets are not exposed to the browser.

## Conventions and Constraints

- Every env var used by the app is listed in `.env.example` with a default value — adding a new env var without updating `.env.example` breaks the contract between the template and actual usage.
- Application configuration is accessed exclusively through `env()` inside `config/*.php` files and `config('...')` in application code; direct `$_ENV`/`getenv()` usage is not observed.
- External service configuration is grouped under a dedicated key in `config/services.php` (`external_barang`) rather than scattered across multiple config files.
- The `external_barang.excluded_statuses` config is normalized to uppercase at read time (`array_map('strtoupper', ...)`) before comparison, making the env value case-insensitive.
- `ExternalBarangApiService` strips trailing slashes from the base URL (`rtrim(..., '/')`) before use, so both `http://host.docker.internal:8000` and `http://host.docker.internal:8000/` produce the same result.
- The service constructor accepts optional override arguments (`$baseUrl`, `$timeout`) so tests can inject fake endpoints without touching `.env`.
- Maintenance mode defaults to the `file` driver with `database` store fallback, controlled by `APP_MAINTENANCE_DRIVER` and `APP_MAINTENANCE_STORE`.
- The application locale defaults to Indonesian (`id`) via `APP_LOCALE=id` in `.env.example`, with English as fallback.