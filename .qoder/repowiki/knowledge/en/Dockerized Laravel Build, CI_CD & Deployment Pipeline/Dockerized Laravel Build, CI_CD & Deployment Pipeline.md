---
kind: build_system
name: Dockerized Laravel Build, CI/CD & Deployment Pipeline
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
    - package.json
    - composer.json
    - docker/production/nginx.conf
    - docker/production/php.ini
    - docker/production/supervisord.conf
    - docker/production/entrypoint.sh
---

## Overview

The RSV Helmet project is a Laravel 13 / PHP 8.4 application whose build and deployment are centered around **Docker multi-stage builds**, **GitHub Actions CI/CD**, and **Laravel's Composer/NPM scripts**. There is no Makefile; orchestration lives in `docker-compose.yml` (development) and `docker-compose.prod.yml` (production), with a helper `deploy.sh` for manual server deployments.

## Build System Components

### Docker Images

- `Dockerfile` — development image based on `php:8.4-cli`, installs PHP extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`) via `docker-php-ext-*`, copies Composer binary from a `composer:2` stage, exposes port 8000, and runs `php artisan serve`. It is used by `docker-compose.yml` to build the dev container.
- `Dockerfile.prod` — three-stage production image:
  1. `composer-builder` (`composer:2`): installs only production dependencies (`--no-dev --no-scripts --optimize-autoloader --ignore-platform-reqs`).
  2. `frontend-builder` (`node:22-alpine`): runs `npm ci`, then `npm run build` (Vite). Filament theme requires vendor CSS/views, so it copies `vendor` from stage 1 before building.
  3. `production` (`php:8.4-fpm-alpine`): installs Nginx + Supervisor + PHP extensions, copies app source, vendor assets, and compiled frontend assets into `/var/www/html`, sets ownership to `www-data`, exposes port 80, and runs `supervisord` via `docker/production/entrypoint.sh`.

Runtime configuration files live under `docker/production/`: `nginx.conf`, `php.ini`, `supervisord.conf`, `entrypoint.sh`.

### Compose Files

- `docker-compose.yml` — development stack: builds the local `Dockerfile`, mounts source code as a volume (`.` → `/var/www/html`), runs as host user (`${UID:-1000}:${GID:-1000}`) so generated files remain editable, maps port 8001→8000, depends on a `mariadb:11` service with a healthcheck.
- `docker-compose.prod.yml` — production stack: pulls prebuilt image `${IMAGE_NAME:-ghcr.io/dikksap/laravel_rsvhelmet:latest}`, uses named volumes (`rsv_helmet_db_data`, `rsv_helmet_app_storage`, `rsv_helmet_app_logs`), configures MariaDB with `utf8mb4_unicode_ci` collation and `+07:00` timezone, sets session/cache/queue to `database`, and passes environment variables through `.env`.

### Frontend Asset Pipeline

- `package.json` declares Vite 8 + Tailwind v4 + `laravel-vite-plugin`; scripts are `build` (`vite build`) and `dev` (`vite`).
- `vite.config.js` drives asset compilation; the production Dockerfile invokes `npm run build` inside the Node stage.

### Composer Scripts

`composer.json` defines:
- `setup` — install deps, copy `.env.example`, generate key, migrate, npm install + build.
- `dev` — runs `artisan dev` with process timeout disabled.
- `test` — clears config then runs `artisan test`.
- `post-autoload-dump` — discovers packages and runs `filament:upgrade`.
- `post-update-cmd` — publishes Laravel assets.
- `post-root-package-install` / `post-create-project-cmd` — bootstrap `.env`, SQLite DB, and migrations.

Dev tooling includes `laravel/pint` (code style), `laravel/pail`, `laravel/pao`, `mockery`, `nunomaduro/collision`, `phpunit/phpunit ^12.5.12`.

### CI/CD Pipeline (GitHub Actions)

`.github/workflows/ci-cd.yml` defines three jobs:

1. **test** (runs on push/PR to `master` and `workflow_dispatch`):
   - Checks out code, sets up PHP 8.4 (with required extensions) and Node.js 22.
   - Caches Composer deps keyed on `composer.lock`.
   - Runs `composer install`, `npm ci && npm run build`.
   - Enforces code style via `vendor/bin/pint --test`.
   - Prepares `.env` from `.env.example`, generates `APP_KEY`, then runs `php artisan test --compact` against an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).

2. **build-and-push** (only on non-PR events):
   - Uses `docker/setup-buildx-action@v3`.
   - Logs in to GHCR using `docker/login-action@v3` with `secrets.GITHUB_TOKEN`.
   - Extracts metadata via `docker/metadata-action@v5` producing tags `latest` (on `refs/heads/master`), `sha` (short commit), and tag ref.
   - Builds and pushes `Dockerfile.prod` with GitHub Actions cache (`type=gha`).

3. **deploy** (only on `refs/heads/master`, non-PR):
   - Conditionally checks for `SSH_HOST` secret; if absent, skips.
   - SSHes into the remote server (`appleboy/ssh-action@v1.2.0`) and runs `docker compose pull`, `up -d --remove-orphans`, then caches configs/routes/views via `php artisan config:cache | route:cache | view:cache`.

### Manual Deployment

`deploy.sh` is a convenience script that:
- Requires `.env` to exist (exits with error otherwise).
- Pulls the latest image, starts containers, waits 5 seconds, then runs `config:cache`, `route:cache`, `view:cache`, and `storage:link`.

## Versioning Strategy

- Docker image tagging follows semantic versioning via Git tags: `v*.*.*` triggers the pipeline and produces a tag-based image name alongside `latest` and short SHA.
- No explicit application version number is embedded in the image labels or metadata; the OCI source label points to the GitHub repo URL.

## Conventions Observed

- Development uses a bind-mounted source volume with host UID/GID so file permissions stay consistent with the host.
- Production images are multi-stage, separating dependency installation, frontend build, and runtime to minimize final image size.
- All environment-sensitive values (DB credentials, `APP_KEY`, API URLs) are passed through `.env` and docker-compose variable interpolation; missing required vars like `DB_PASSWORD` and `APP_KEY` cause compose to fail at startup.
- The CI test suite runs against SQLite in-memory, not MySQL/MariaDB, keeping the CI job lightweight.
- Code style enforcement (`pint --test`) is a hard gate in CI before tests run.
- Frontend assets are built both in CI (for tests) and in the production Docker image; the two builds use separate stages but share the same `vite.config.js`.
- Database schema changes go through Laravel migrations under `database/migrations/`, timestamped with future dates (`2026_...`), indicating forward-dated migration ordering.