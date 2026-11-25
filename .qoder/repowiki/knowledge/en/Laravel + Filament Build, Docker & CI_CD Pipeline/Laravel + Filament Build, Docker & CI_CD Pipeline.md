---
kind: build_system
name: Laravel + Filament Build, Docker & CI/CD Pipeline
category: build_system
scope:
    - '**'
source_files:
    - Dockerfile
    - Dockerfile.prod
    - docker-compose.yml
    - docker-compose.prod.yml
    - .github/workflows/ci-cd.yml
    - package.json
    - vite.config.js
    - composer.json
    - phpunit.xml
    - deploy.sh
    - docker/production/nginx.conf
    - docker/production/supervisord.conf
    - docker/production/php.ini
    - docker/production/entrypoint.sh
---

## Overview

This Laravel 13 / Filament 5 application uses a multi-stage Docker build driven by GitHub Actions. The pipeline installs PHP dependencies via Composer, builds frontend assets with Vite (Tailwind CSS 4), runs PHPUnit tests against an in-memory SQLite database, then publishes a multi-arch image to GitHub Container Registry (GHCR) and optionally deploys to a remote server via SSH.

## Key Files

- `Dockerfile` — development container (PHP 8.4-cli, artisan serve on port 8000).
- `Dockerfile.prod` — production multi-stage image: composer-builder → node frontend-builder → php:8.4-fpm-alpine runtime with Nginx + Supervisor.
- `docker-compose.yml` — local dev stack (`app` + `mariadb:11`, host port 8001).
- `docker-compose.prod.yml` — production compose with persistent volumes (`rsv_helmet_db_data`, `rsv_helmet_app_storage`, `rsv_helmet_app_logs`), MariaDB with `utf8mb4_unicode_ci` collation and `+07:00` timezone.
- `.github/workflows/ci-cd.yml` — the single CI/CD workflow.
- `package.json` — Vite 8 + Tailwind CSS 4 entry points (`resources/css/app.css`, `resources/css/filament/admin/theme.css`, `resources/js/app.js`).
- `vite.config.js` — Vite config, dev server on port 5174.
- `composer.json` — PHP dependency manifest; scripts include `setup`, `dev`, `test`, plus Composer hooks for filament upgrades and asset publishing.
- `phpunit.xml` — test suite configuration with in-memory SQLite.
- `deploy.sh` — manual deployment helper script.
- `docker/production/` — Nginx config, PHP ini, Supervisor config, entrypoint.

## Build Architecture

### Multi-stage Dockerfile.prod

Three stages are chained:

1. **composer-builder** (`composer:2`): installs only production deps (`--no-dev --no-scripts --optimize-autoloader --ignore-platform-reqs`) from `composer.json` + `composer.lock`.
2. **frontend-builder** (`node:22-alpine`): runs `npm ci`, then copies vendor from stage 1 (Filament requires vendor CSS/templates for its theme build) and runs `npm run build` to emit `public/build`.
3. **production** (`php:8.4-fpm-alpine`): installs Nginx, Supervisor, MySQL client, and PHP extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`); copies app source, vendor, and compiled assets; sets ownership to `www-data` with `775` on `storage` and `bootstrap/cache`; exposes port 80; entrypoint runs Supervisor.

The development `Dockerfile` is intentionally minimal — it just installs PHP extensions and runs `php artisan serve` on `0.0.0.0:8000`.

### Frontend Asset Pipeline

Vite is configured via `laravel-vite-plugin` with three inputs: `resources/css/app.css`, `resources/css/filament/admin/theme.css`, and `resources/js/app.js`. Tailwind CSS v4 is used through `@tailwindcss/vite`. Fonts use the Bunny CDN integration (`Instrument Sans`, weights 400/500/600). Dev server runs on port 5174 with `strictPort: true` and ignores Laravel's compiled view cache directory.

### Composer Scripts

- `composer setup`: runs full bootstrap (install deps, copy `.env.example`, generate key, migrate, npm install + build).
- `composer dev`: disables process timeout and runs `artisan dev`.
- `composer test`: clears config then runs `artisan test`.
- `post-autoload-dump`: discovers packages and runs `filament:upgrade`.
- `post-update-cmd`: republishes Laravel assets.
- `post-root-package-install` / `post-create-project-cmd`: seed initial `.env`, key, sqlite DB, and migrations.

Composer config enforces `sort-packages: true`, `optimize-autoloader: true`, `preferred-install: dist`, and minimum stability `stable` with `prefer-stable: true`.

## CI/CD Pipeline (`.github/workflows/ci-cd.yml`)

Three jobs run sequentially:

1. **test** (runs on every push/PR):
   - Sets up PHP 8.4 with required extensions and Node.js 22.
   - Caches Composer deps keyed on `composer.lock`.
   - Runs `composer install`, `npm ci`, `npm run build`.
   - Enforces code style via `vendor/bin/pint --test`.
   - Copies `.env.example` to `.env`, runs `php artisan key:generate`.
   - Executes `php artisan test --compact` with `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.

2. **build-and-push** (skipped on PRs):
   - Uses `docker/setup-buildx-action@v3`.
   - Logs into GHCR using `secrets.GITHUB_TOKEN`.
   - Generates tags via `docker/metadata-action`: `latest` on `refs/heads/master`, short SHA, and tag ref.
   - Builds with `docker/build-push-action@v6` using `./Dockerfile.prod` with GHA cache (`type=gha,mode=max`).

3. **deploy** (only on pushes to `master`, not PRs):
   - Conditionally checks for `SSH_HOST` secret; if absent, skips.
   - Uses `appleboy/ssh-action@v1.2.0` to SSH into `/opt/web/RSV_Helmet_Laravel`, pull the latest image, run `docker compose -f docker-compose.prod.yml up -d --remove-orphans`, then cache configs/routes/views inside the container.

## Deployment Conventions

- Production images are published to `ghcr.io/dikksap/laravel_rsvhelmet`.
- The production compose file expects environment variables for `APP_KEY`, `DB_PASSWORD`, `EXTERNAL_BARANG_API_URL`; missing required vars cause Compose to fail at startup.
- Database defaults to MariaDB 11 with `utf8mb4_unicode_ci` collation and `Asia/Jakarta` timezone.
- Storage and logs are persisted via named volumes (`rsv_helmet_app_storage`, `rsv_helmet_app_logs`, `rsv_helmet_db_data`).
- Manual deployment is done via `deploy.sh`, which pulls the image, starts containers, waits 5 seconds, then caches config/route/view and creates the storage symlink.
- The CI deploy step mirrors this flow on the remote server under `/opt/web/RSV_Helmet_Laravel`.

## Testing Setup

- Tests use SQLite in-memory database (`:memory:`) — no external DB needed.
- Cache/session/mail/queue drivers are set to `array`/`null`/`sync` for speed.
- Bcrypt rounds lowered to 4 for faster test execution.
- Test source coverage is scoped to `app/`.

## Constraints Observed

- PHP version is pinned to `^8.3` in `composer.json` and built with PHP 8.4 in both CI and Dockerfiles.
- Node.js is pinned to 22 across CI and the production frontend builder.
- Composer lockfile is committed and used as the cache key in CI.
- Pint is enforced as a gate (`pint --test`) before tests run.
- Production builds ignore platform requirements (`--ignore-platform-reqs`) during Composer install.
- Filament v5 requires vendor assets to be present during the Vite build stage.