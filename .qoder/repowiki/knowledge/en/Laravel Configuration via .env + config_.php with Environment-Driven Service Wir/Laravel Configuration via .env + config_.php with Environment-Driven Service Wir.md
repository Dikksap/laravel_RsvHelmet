---
kind: configuration_system
name: Laravel Configuration via .env + config/*.php with Environment-Driven Service Wiring
category: configuration_system
scope:
    - '**'
source_files:
    - .env.example
    - bootstrap/app.php
    - config/app.php
    - config/database.php
    - config/cache.php
    - config/session.php
    - config/queue.php
    - config/logging.php
    - config/filesystems.php
    - config/services.php
---

## Approach

The application uses the standard Laravel configuration system: runtime settings are loaded from `.env` (dotenv) and merged into PHP arrays under `config/`. There is no custom configuration loader, feature-flag service, or YAML/TOML layer — all configuration flows through `Illuminate\Support\Env::get()` (`env()` helper).

## Key Files

- `bootstrap/app.php` — Application bootstrap; registers middleware that trusts reverse-proxy headers (`X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Port`) because production runs behind Cloudflare Tunnel / a TLS-terminating proxy.
- `.env.example` — Single source of truth for every environment variable the app consumes.
- `config/app.php` — App name, env, debug, URL, locale/fallback_locale/faker_locale, encryption key/cipher, previous_keys (comma-separated), maintenance driver/store.
- `config/database.php` — Default connection (`sqlite` in config, overridden by `.env` to `mysql`); connections for sqlite/mysql/mariadb/pgsql/sqlsrv; Redis default+cache subconnections with retry/backoff options.
- `config/cache.php` — Default store `database`; stores: array, database, file, storage, memcached, redis, dynamodb, octane, failover (database→array). Prefix derived from `APP_NAME` slug.
- `config/session.php` — Driver `database`, JSON serialization, cookie name derived from `APP_NAME`, path `/`, SameSite `lax`, http_only true, secure/http-only/partitioned toggles via env.
- `config/queue.php` — Default `database`; connections sync/database/beanstalkd/sqs/redis/deferred/background/failover; failed jobs stored via `database-uuids`.
- `config/logging.php` — Default channel `stack` composed from comma-separated `LOG_STACK`; channels single/daily/monthly/slack/papertrail/stderr/syslog/errorlog/null/emergency; deprecations routed to `null` by default.
- `config/filesystems.php` — Disks `local` (private), `public` (URL built from `APP_URL` + `/storage`), `s3` (AWS keys/bucket/endpoint/path-style).
- `config/services.php` — Third-party credentials: postmark, resend, ses (AWS), slack notifications, plus a domain-specific `external_barang` section (`base_url`, `timeout`, `excluded_statuses`, `default_warehouse_id`).
- `config/mail.php`, `config/auth.php` — Framework defaults; mailer defaults to `log` in `.env.example`.

## Architecture & Conventions

1. **Single `.env` surface.** All runtime values live in `.env.example`; there is no per-environment `config/*.php` override file. The example covers application identity, DB, session, cache, queue, broadcast, filesystem, logging, mail, AWS, Vite, and the domain-specific `EXTERNAL_BARANG_API_*` variables.
2. **Config files read env with defaults.** Every value in `config/*.php` is sourced via `env('VAR', <default>)`, so the repository ships with safe defaults (e.g. `DB_CONNECTION=sqlite`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `FILESYSTEM_DISK=local`, `MAIL_MAILER=log`).
3. **Environment variables are the only secrets boundary.** Secrets (DB password, AWS keys, API tokens, `APP_KEY`) are never committed — they are expected to be supplied at deploy time (the `.env.example` has placeholder values like `changeme`).
4. **Service wiring lives in `config/services.php`.** External integrations (Postmark, Resend, SES, Slack, and the `external_barang` API client) are registered as named service configs consumed by their respective packages/services rather than inline in business code.
5. **Domain-specific configuration grouped under a dedicated key.** The external barang integration is configured as `services.external_barang.*` (`base_url`, `timeout`, `excluded_statuses`, `default_warehouse_id`), keeping third-party URLs and flags out of `.env` scattering.
6. **Reverse-proxy awareness is explicit.** `bootstrap/app.php` calls `$middleware->trustProxies(at: '*', ...)` with forwarded-for/proto/port headers, documented in a comment explaining that Cloudflare Tunnel terminates TLS and Laravel would otherwise see HTTP-only URLs.
7. **No feature-flag framework.** Feature toggles are not implemented as a flag system; instead, behavior is driven by env/config values (e.g. `APP_DEBUG`, `APP_MAINTENANCE_DRIVER`, `EXCLUDED_STATUSES` in services config). There is no separate feature-flag table or service class.
8. **Vite bridge.** `VITE_APP_NAME` in `.env.example` forwards `APP_NAME` to the frontend build via Vite.
9. **Dockerized defaults.** `.env.example` targets Docker Compose hostnames (`DB_HOST=db`, `REDIS_HOST=127.0.0.1`, `EXTERNAL_BARANG_API_URL=http://host.docker.internal:8000`), indicating development runs inside containers.

## Conventions & Constraints

- **All runtime configuration goes through `env()` in `config/*.php`.** No direct `$_ENV` or `getenv()` usage was observed in the configuration layer.
- **Secrets are not committed.** `.env` itself is gitignored (standard Laravel); only `.env.example` is tracked, containing placeholders.
- **Database, cache, session, and queue backends are selected via env vars** (`DB_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`), allowing backend swapping without code changes.
- **Redis is configured with retry/backoff tuning** (`REDIS_MAX_RETRIES`, `REDIS_BACKOFF_ALGORITHM`, `REDIS_BACKOFF_BASE`, `REDIS_BACKOFF_CAP`) across both default and cache connections in `config/database.php`.
- **Session data is serialized as JSON** (`session.php` hardcodes `'serialization' => 'json'`) to avoid PHP object deserialization gadget-chain attacks when `APP_KEY` leaks.
- **Cache keys are prefixed with the slugified `APP_NAME`** (`Str::slug(APP_NAME) . '-cache-'`) to avoid collisions between apps sharing a cache server.
- **Filesystem public disk URL is derived from `APP_URL` + `/storage`**, ensuring consistency with the application root.
- **Logging stack is composable**: `LOG_STACK` accepts a comma-separated list of channel names, enabling multi-destination logging via the `stack` driver.
- **Maintenance mode supports file or cache-backed drivers** (`APP_MAINTENANCE_DRIVER=file|cache|array`, `APP_MAINTENANCE_STORE=database`), chosen via env.
- **External service credentials follow the Laravel convention** of living under `config/services.php` keys (`postmark`, `resend`, `ses`, `slack`) and reading from corresponding env vars (`POSTMARK_API_KEY`, `RESEND_API_KEY`, `AWS_ACCESS_KEY_ID`, etc.).
- **Production deployment relies on a reverse proxy terminating TLS**; the trust-proxies middleware is mandatory for correct HTTPS URL generation in Livewire/Filament login flows.