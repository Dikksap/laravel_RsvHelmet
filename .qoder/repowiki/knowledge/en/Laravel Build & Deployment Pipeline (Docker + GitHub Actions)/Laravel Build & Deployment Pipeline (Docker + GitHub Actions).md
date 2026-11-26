---
kind: build_system
name: Laravel Build & Deployment Pipeline (Docker + GitHub Actions)
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
    - phpunit.xml
    - docker/production/nginx.conf
    - docker/production/supervisord.conf
    - docker/production/entrypoint.sh
---

## Overview

This Laravel 13 application uses a multi-stage Docker build pipeline driven by GitHub Actions. The build combines PHP Composer dependency installation, Vite frontend asset compilation, and container image publishing to GitHub Container Registry (GHCR), with optional SSH-based auto-deploy.

## Build Tools & Frameworks

- **PHP runtime**: `php:8.4-fpm-alpine` for production, `php:8.4-cli` for development (`Dockerfile`, `Dockerfile.prod`).
- **Composer** (`composer:2`) for PHP dependencies; installed in a dedicated stage and copied into the final image.
- **Node.js 22** (`node:22-alpine`) for Vite asset building; runs `npm ci` then `npm run build` (`vite.config.js`).
- **Nginx + Supervisor** serve the app inside the production container (`docker/production/nginx.conf`, `supervisord.conf`, `entrypoint.sh`).
- **MariaDB 11** is the database service in both dev and prod compose files.

## Key Files

| File | Role |
|---|---|
| `Dockerfile` | Development image — PHP CLI + `artisan serve` on port 8000 |
| `Dockerfile.prod` | Multi-stage production image (Composer → Node/Vite → PHP-FPM+Nginx+Supervisor) |
| `docker-compose.yml` | Local dev stack (`app` + `db:mariadb:11`) with host volume mount and user UID/GID passthrough |
| `docker-compose.prod.yml` | Production stack using prebuilt GHCR image, named volumes for storage/logs, bridge network `rsv_network` |
| `.github/workflows/ci-cd.yml` | CI/CD pipeline: test → build/push → deploy |
| `deploy.sh` | Manual server-side deployment script |
| `package.json` | Vite scripts (`build`, `dev`) and Tailwind/vite plugin deps |
| `composer.json` | PHP deps, autoload PSR-4, Composer scripts (`setup`, `dev`, `test`) |
| `phpunit.xml` | PHPUnit configuration |

## Architecture of the Build Pipeline

### Multi-stage Dockerfile.prod

Three stages are chained:

1. **`composer-builder`** — installs `vendor/` with `--no-dev --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs`.
2. **`frontend-builder`** — installs Node deps via `npm ci`, copies `vendor/` from stage 1 (Filament theme needs it), then runs `npm run build` to emit `public/build`.
3. **`production`** — Alpine base with Nginx, Supervisor, PHP extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`), copies source + vendor + compiled assets, sets `www-data` ownership on `storage` and `bootstrap/cache`, exposes port 80, entrypoint runs Supervisor.

### GitHub Actions Workflow (`.github/workflows/ci-cd.yml`)

Three jobs run sequentially:

1. **`test`** (runs on every push/PR):
   - Sets up PHP 8.4 and Node 22.
   - Caches Composer files via `actions/cache@v4` keyed on `composer.lock`.
   - Runs `composer install`, `npm ci`, `npm run build`.
   - Enforces code style with `vendor/bin/pint --test`.
   - Generates `.env` + `APP_KEY`, then runs `php artisan test --compact` against an in-memory SQLite DB (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).

2. **`build-and-push`** (skipped on PRs):
   - Uses `docker/setup-buildx-action@v3`.
   - Logs in to GHCR with `${{ secrets.GITHUB_TOKEN }}`.
   - Tags images as `latest` (only on `refs/heads/master`), short SHA, and tag ref.
   - Builds `./Dockerfile.prod` with GitHub Actions cache (`type=gha,mode=max`).

3. **`deploy`** (only on master pushes, not PRs):
   - Conditionally enabled if `secrets.SSH_HOST` exists.
   - SSHes into the remote server (`/opt/web/RSV_Helmet_Laravel`), logs into GHCR, pulls the image via `docker compose -f docker-compose.prod.yml pull app`, brings containers up with `up -d --remove-orphans`, then caches config/routes/views and links storage.

### Local Development vs Production Compose

- **Dev** (`docker-compose.yml`): mounts source code as a volume, maps host port `8001` → container `8000`, runs as the host user (`${UID:-1000}:${GID:-1000}`) so generated files retain host ownership.
- **Prod** (`docker-compose.prod.yml`): pulls a prebuilt image from `${IMAGE_NAME:-ghcr.io/dikksap/laravel_rsvhelmet:latest}`, maps host port `${APP_PORT:-8001}` → container `80`, persists `app_storage` and `app_logs` as named volumes, sets MariaDB charset/collation/timezone (`utf8mb4_unicode_ci`, `+07:00`, `Asia/Jakarta`).

### Manual Deployment Script

`deploy.sh` performs the same steps as the CI deploy job: validates `.env` exists, pulls the latest image, starts containers, waits 5 seconds, then runs `config:cache`, `route:cache`, `view:cache`, and `storage:link`.

## Conventions & Constraints

- **PHP version pinning**: both the CI runner and production Docker image target PHP 8.4; `composer.json` requires `^8.3`.
- **Frontend toolchain**: Vite 8 + Tailwind CSS 4 via `laravel-vite-plugin`; assets are built once and baked into the image — no runtime bundling.
- **Dependency caching**: Composer cache path is resolved via `composer config cache-files-dir` and cached on `runner.os-composer-{hash of composer.lock}`; Docker layer cache uses `type=gha,mode=max`.
- **Test isolation**: All tests run against an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`); no external DB is required for CI.
- **Image tagging policy**: `latest` tag is only applied on pushes to `refs/heads/master`; all builds also get a short SHA tag and any git tag matching `v*.*.*` becomes an image tag.
- **Secrets gating**: Automated SSH deployment is disabled unless `secrets.SSH_HOST` is set; the workflow prints a skip message otherwise.
- **Environment variable enforcement**: Prod compose file uses `?` syntax (e.g., `${DB_PASSWORD:?DB_PASSWORD wajib diset}`, `${APP_KEY:?APP_KEY wajib diset}`) to fail fast if required env vars are missing.
- **Production permissions**: Final image chowns `storage` and `bootstrap/cache` to `www-data` with mode `775`.
- **Locale**: Production defaults to Indonesian (`APP_LOCALE=id`, `APP_FALLBACK_LOCALE=en`).