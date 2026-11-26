---
kind: build_system
name: Build, Containerization & CI/CD Pipeline (Laravel + Filament)
category: build_system
scope:
    - '**'
source_files:
    - Dockerfile
    - Dockerfile.prod
    - docker-compose.yml
    - docker-compose.prod.yml
    - .github/workflows/ci-cd.yml
    - deploy.sh
    - composer.json
    - package.json
    - vite.config.js
    - phpunit.xml
---

## Build System Overview

The project is a Laravel 13 / Filament 5 application built with a multi-stage Docker pipeline and GitHub Actions. There are two build targets: a development image (`Dockerfile`) that runs `php artisan serve`, and a production image (`Dockerfile.prod`) that bundles Nginx + PHP-FPM + Supervisor.

### Tools & Frameworks
- **PHP**: `^8.3` runtime, pinned to `php:8.4-cli` / `php:8.4-fpm-alpine` in images.
- **Composer** (`composer:2`): dependency resolution for PHP packages; `composer.lock` is committed and used as the cache key in CI.
- **Node.js 22 + Vite 8**: frontend build via `laravel-vite-plugin`; Tailwind CSS v4 is compiled into `public/build`.
- **Docker**: multi-stage builds; `docker-compose.yml` for local dev (MariaDB 11), `docker-compose.prod.yml` for production.
- **GitHub Actions** (`.github/workflows/ci-cd.yml`): test → build/push to GHCR → optional SSH deploy.
- **PHPUnit 12** + **Laravel Pint** for testing and code style.

### Key Files
- `Dockerfile` — development container (PHP CLI + `artisan serve` on port 8000).
- `Dockerfile.prod` — three-stage production build:
  1. `composer-builder`: installs prod-only Composer deps (`--no-dev --no-scripts --optimize-autoloader`).
  2. `frontend-builder` (`node:22-alpine`): runs `npm ci` then `npm run build`, copying vendor assets needed by Filament's custom theme.
  3. `production` (`php:8.4-fpm-alpine`): Nginx + Supervisor + PHP extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`).
- `docker-compose.yml` — local dev: `app` service (bind-mounts source, maps 8001→8000) + MariaDB 11 with healthcheck.
- `docker-compose.prod.yml` — production compose: pulls prebuilt image from GHCR (`${IMAGE_NAME:-ghcr.io/dikksap/laravel_rsvhelmet:latest}`), mounts named volumes for storage/logs, sets env vars (`APP_ENV=production`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`).
- `.github/workflows/ci-cd.yml` — three jobs: `test`, `build-and-push`, `deploy`.
- `deploy.sh` — manual deployment script mirroring the CI deploy steps.
- `package.json` — scripts `build` (`vite build`) and `dev` (`vite`).
- `composer.json` — scripts `setup`, `dev`, `test`; hooks `post-autoload-dump` runs `filament:upgrade`; `post-update-cmd` publishes Laravel assets.
- `vite.config.js` — inputs: `resources/css/app.css`, `resources/css/filament/admin/theme.css`, `resources/js/app.js`; dev server on port 5174.
- `phpunit.xml` — Unit + Feature suites, SQLite `:memory:` DB for tests.

### CI/CD Flow (`.github/workflows/ci-cd.yml`)

1. **test** (runs on every push/PR to `master`):
   - Setup PHP 8.4 with extensions (`mbstring, xml, ctype, iconv, intl, pdo, pdo_sqlite, zip, bcmath, gd`) and Node.js 22.
   - Cache Composer deps keyed on `composer.lock` hash.
   - Run `composer install`, `npm ci`, `npm run build`.
   - Enforce code style via `vendor/bin/pint --test`.
   - Generate app key, then run `php artisan test --compact` against an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).

2. **build-and-push** (only on non-PR pushes):
   - Uses `docker/setup-buildx-action@v3`.
   - Logs in to GHCR via `docker/login-action@v3`.
   - Generates tags via `docker/metadata-action@v5`: `latest` (only on `refs/heads/master`), short SHA, and tag ref.
   - Builds `Dockerfile.prod` with GHA cache (`type=gha,mode=max`).

3. **deploy** (only on `refs/heads/master`):
   - Conditionally enabled only if `secrets.SSH_HOST` exists.
   - SSHes to the server (`appleboy/ssh-action@v1.2.0`) and runs:
     ```bash
     cd /opt/web/RSV_Helmet_Laravel
     docker login ghcr.io ... 
     docker compose -f docker-compose.prod.yml pull app
     docker compose -f docker-compose.prod.yml up -d --remove-orphans
     docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
     docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
     docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache
     ```

### Conventions & Constraints

- **Multi-stage production build**: Frontend assets are compiled in a separate Node stage and copied into the final image; PHP vendor dir is also staged separately so the runtime image contains only prod dependencies.
- **No dev dependencies in production**: `composer install --no-dev --no-scripts` in the builder stage.
- **Filament theme build requirement**: The frontend builder copies `vendor`, `resources`, and `app` because "Filament custom theme requires vendor CSS & view templates to build" (comment in `Dockerfile.prod`).
- **Runtime processes**: Supervisor manages Nginx and PHP-FPM; entrypoint script at `docker/production/entrypoint.sh` is invoked before `supervisord`.
- **Environment-driven configuration**: Production compose uses `${VAR:-default}` syntax for all env vars; required secrets (`APP_KEY`, `DB_PASSWORD`) use `${VAR:?error message}` to fail fast if unset.
- **Database**: MariaDB 11 with `utf8mb4_unicode_ci` collation and `+07:00` timezone; healthcheck uses `healthcheck.sh --connect --innodb_initialized`.
- **Storage persistence**: Named volumes `rsv_helmet_db_data`, `rsv_helmet_app_storage`, `rsv_helmet_app_logs` in production compose.
- **Test isolation**: Tests run against an in-memory SQLite database; cache/session/mail/queue drivers are set to `array`/`sync`/`array` in `phpunit.xml`.
- **Code quality gate**: `vendor/bin/pint --test` fails the CI job if code style is violated.
- **Image tagging policy**: `latest` tag is only pushed for the `master` branch; every commit gets a short-SHA tag; versioned tags (`v*.*.*`) produce a matching tag.
- **Deployment script parity**: `deploy.sh` mirrors the CI deploy sequence (pull → up → cache commands) for manual deployments.
- **PHP extension set**: Both dev and prod images install the same core extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`), ensuring parity between local and production environments.