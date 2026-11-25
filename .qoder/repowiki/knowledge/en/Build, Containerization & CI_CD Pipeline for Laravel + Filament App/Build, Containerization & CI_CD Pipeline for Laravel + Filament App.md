---
kind: build_system
name: Build, Containerization & CI/CD Pipeline for Laravel + Filament App
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
    - phpunit.xml
    - docker/production/nginx.conf
    - docker/production/php.ini
    - docker/production/supervisord.conf
    - docker/production/entrypoint.sh
---

# Build System Overview

This project is a Laravel 13 / Filament 5 application built with a multi-stage Docker pipeline, Vite for frontend assets, and GitHub Actions for CI/CD. There is no Makefile; orchestration lives in shell scripts, Dockerfiles, and the GitHub Actions workflow.

## Tools and Frameworks

- **PHP runtime**: PHP 8.4 (FPM for production, CLI for dev/build).
- **Composer** (`composer.json`): manages PHP dependencies; `laravel/framework ^13.17`, `filament/filament ^5.0`, plus dompdf, infinite-scroll/select packages.
- **Node.js 22 + Vite 8** (`package.json`): builds Tailwind CSS 4 and Filament custom theme into `public/build`.
- **Docker**: two images — `Dockerfile` (development, runs `php artisan serve` on port 8000) and `Dockerfile.prod` (production, Nginx + PHP-FPM + Supervisor).
- **docker-compose**: `docker-compose.yml` for local dev (app + MariaDB 11), `docker-compose.prod.yml` for production deployment.
- **GitHub Actions** (`.github/workflows/ci-cd.yml`): test → build → push to GHCR → optional SSH deploy.
- **PHPUnit 12** (`phpunit.xml`): tests run against SQLite in-memory DB.
- **Laravel Pint**: code style checked via `vendor/bin/pint --test`.

## Key Files

| File | Role |
|---|---|
| `Dockerfile` | Dev image: PHP 8.4 CLI, exposes 8000, runs `artisan serve` |
| `Dockerfile.prod` | Multi-stage prod image: Composer stage → Node/Vite stage → PHP-FPM+Nginx+Supervisor runtime |
| `docker-compose.yml` | Local dev stack (app + MariaDB 11, host user UID/GID passthrough) |
| `docker-compose.prod.yml` | Production stack with env-driven config, persistent volumes for storage/logs/db |
| `.github/workflows/ci-cd.yml` | Full CI/CD pipeline |
| `deploy.sh` | Manual server-side deployment script |
| `composer.json` | PHP deps, autoload, Composer scripts (`setup`, `dev`, `test`) |
| `package.json` | Vite build/dev scripts, Tailwind 4 + Filament plugin |
| `phpunit.xml` | PHPUnit configuration |
| `vite.config.js` | Vite/Filament asset build config |
| `docker/production/*` | Nginx config, PHP ini, Supervisor conf, entrypoint script |

## Multi-Stage Build Architecture (`Dockerfile.prod`)

Three distinct stages are chained:

1. **`composer-builder`** — installs only production PHP deps (`--no-dev --no-scripts --optimize-autoloader --ignore-platform-reqs`).
2. **`frontend-builder`** — Node 22 Alpine, runs `npm ci` then `npm run build`; requires vendor CSS and view templates from stage 1 to compile Filament's custom theme.
3. **`production`** — PHP 8.4 FPM Alpine with Nginx, Supervisor, GD/bcmath/intl/zip/opcache/pcntl extensions; copies compiled assets from stage 2 and vendor from stage 1.

The runtime container uses Supervisor to manage Nginx and PHP-FPM processes, with an entrypoint script that performs runtime setup before launching Supervisor.

## CI/CD Pipeline (`.github/workflows/ci-cd.yml`)

Three jobs run sequentially:

1. **`test`** (runs on every push/PR):
   - Sets up PHP 8.4 with required extensions and Node.js 22.
   - Caches Composer and npm directories.
   - Runs `composer install`, `npm ci && npm run build`.
   - Enforces code style with `vendor/bin/pint --test`.
   - Generates app key and runs `php artisan test --compact` using SQLite in-memory DB (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).

2. **`build-and-push`** (only on non-PR pushes):
   - Builds the image using `./Dockerfile.prod`.
   - Pushes to `ghcr.io/dikksap/laravel_rsvhelmet` with tags: `latest` (master branch only), short SHA, and git tag ref.
   - Uses Docker Buildx with GitHub Actions cache (`type=gha`).

3. **`deploy`** (only on master pushes):
   - Conditionally enabled if `secrets.SSH_HOST` exists.
   - SSHes into the remote server (`/opt/web/RSV_Helmet_Laravel`), pulls the latest image from GHCR, runs `docker compose -f docker-compose.prod.yml up -d --remove-orphans`, then caches configs/routes/views inside the container.

## Development vs Production Compose

- **Local (`docker-compose.yml`)**: mounts source as volume (`.` → `/var/www/html`), runs as host UID/GID so generated files remain writable by the host user, forwards port 8001→8000.
- **Production (`docker-compose.prod.yml`)**: pulls prebuilt image `${IMAGE_NAME:-ghcr.io/dikksap/laravel_rsvhelmet:latest}`, maps port `${APP_PORT:-8001}:80`, persists `storage/app/public` and `storage/logs` via named volumes, sets MariaDB charset/collation/timezone (`utf8mb4_unicode_ci`, `+07:00`, `Asia/Jakarta`).

## Deployment Scripts

- `deploy.sh`: manual server-side script that checks for `.env`, pulls the image, starts containers, waits 5 seconds, then runs `config:cache`, `route:cache`, `view:cache`, and `storage:link`.
- The CI `deploy` job mirrors this flow but via SSH action.

## Conventions and Constraints

- **PHP version**: pinned to 8.4 across all environments (CLI, FPM, GitHub Actions `shivammathur/setup-php@v2`).
- **Frontend toolchain**: Vite 8 + Tailwind 4 + Filament custom theme; assets must be rebuilt whenever `resources/` or `app/` (Filament views) change.
- **Dependency locking**: both `composer.lock` and `package-lock.json` are committed; CI uses `npm ci` (not `npm install`) for deterministic installs.
- **Test isolation**: all tests use SQLite in-memory database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) — no external DB needed for CI.
- **Code quality gate**: `vendor/bin/pint --test` fails the CI if code style is violated.
- **Image tagging strategy**: `latest` tag is only applied on `refs/heads/master`; other pushes get SHA and tag refs via `docker/metadata-action`.
- **Deployment target**: automated SSH deploy targets `/opt/web/RSV_Helmet_Laravel` on the remote server and expects `docker-compose.prod.yml` to exist there.
- **Environment variables**: production compose requires `APP_KEY`, `DB_PASSWORD`, and `SSH_*` secrets; missing values abort via `${VAR:?message}` syntax.
- **Database**: MariaDB 11 with `utf8mb4` charset, `utf8mb4_unicode_ci` collation, timezone `+07:00`.
- **Composer scripts**: `composer setup` is the canonical bootstrap sequence (install deps, copy `.env.example`, generate key, migrate, build assets); `composer test` clears config before running PHPUnit.