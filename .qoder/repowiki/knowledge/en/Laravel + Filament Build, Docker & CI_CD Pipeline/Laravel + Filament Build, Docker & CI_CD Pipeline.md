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
    - deploy.sh
    - package.json
    - vite.config.js
    - composer.json
    - phpunit.xml
---

## Build System Overview

This Laravel 13 / Filament 5 application uses a multi-stage Docker build driven by GitHub Actions. The build pipeline compiles PHP dependencies via Composer, builds frontend assets with Vite (Tailwind CSS), runs PHPUnit tests against an in-memory SQLite database, and publishes images to GitHub Container Registry (GHCR).

### Tools & Frameworks
- **PHP**: `php:8.4-fpm-alpine` runtime; extensions installed include `pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`.
- **Composer** (`composer:2`): dependency resolution with `--no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs` for production.
- **Node.js 22** (`node:22-alpine`): Vite 8 + Tailwind CSS 4 build for Filament admin assets.
- **Nginx + Supervisor**: production container serves static assets via Nginx and runs PHP-FPM + queued workers under Supervisor.
- **Docker Compose**: development (`docker-compose.yml`) and production (`docker-compose.prod.yml`) stacks using MariaDB 11.
- **GitHub Actions** (`.github/workflows/ci-cd.yml`): test → build/push → optional SSH deploy.
- **PHPUnit 12** + **Laravel Pint** for testing and code style.

### Multi-Stage Production Dockerfile (`Dockerfile.prod`)
Three stages:
1. **composer-builder** — installs only production Composer deps into `/app/vendor`.
2. **frontend-builder** — installs Node deps, copies Filament vendor templates/CSS, then runs `npm run build` to emit `public/build`.
3. **production** — PHP 8.4 FPM Alpine image with Nginx, Supervisor, and all PHP extensions; copies `.`, vendor from stage 1, and built assets from stage 2.

The development `Dockerfile` is a single-stage `php:8.4-cli` image that runs `php artisan serve --host=0.0.0.0 --port=8000`.

### Compose Stacks
- **Development** (`docker-compose.yml`):
  - `app` service builds from `./Dockerfile`, mounts the source tree as a volume (`.` → `/var/www/html`), exposes port `8001:8000`, runs as host user `${UID:-1000}:${GID:-1000}` so generated files are writable without `sudo`.
  - `db` service uses `mariadb:11` with healthcheck (`healthcheck.sh --connect --innodb_initialized`).
- **Production** (`docker-compose.prod.yml`):
  - Pulls prebuilt image `${IMAGE_NAME:-ghcr.io/dikksap/laravel_rsvhelmet:latest}` on port `${APP_PORT:-8001}:80`.
  - Environment variables cover `APP_*`, `LOG_*`, `DB_*`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, plus `RUN_MIGRATIONS`.
  - Named volumes `rsv_helmet_db_data`, `app_storage`, `app_logs` persist data/logs.
  - Database command sets `utf8mb4_unicode_ci` collation and `+07:00` timezone.

### CI/CD Pipeline (`.github/workflows/ci-cd.yml`)
Three jobs chained by `needs`:

1. **test** (runs on every push/PR to `master`):
   - Sets up PHP 8.4 (shivammathur/setup-php) and Node.js 22.
   - Caches Composer deps keyed on `composer.lock`.
   - Runs `composer install`, `npm ci && npm run build`.
   - Enforces code style via `vendor/bin/pint --test`.
   - Generates `.env` + `APP_KEY`, then runs `php artisan test --compact` with `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.

2. **build-and-push** (only on non-PR pushes):
   - Uses `docker/setup-buildx-action@v3`.
   - Logs into GHCR with `docker/login-action@v3`.
   - Tags via `docker/metadata-action@v5`: `latest` on `refs/heads/master`, short `sha`, and tag ref.
   - Builds `./Dockerfile.prod` with GHA cache (`type=gha`).

3. **deploy** (only on `refs/heads/master`, non-PR):
   - Conditionally checks for `secrets.SSH_HOST`; if absent, skips.
   - Uses `appleboy/ssh-action@v1.2.0` to SSH into the server, login to GHCR, `docker compose -f docker-compose.prod.yml pull app`, `up -d --remove-orphans`, then cache configs/routes/views inside the container.

### Local Deployment Script (`deploy.sh`)
A convenience script that:
- Requires `.env` to exist (exits with error otherwise).
- Pulls latest image, starts containers, waits 5 seconds, then runs `config:cache`, `route:cache`, `view:cache`, `storage:link` inside the app container.

### Frontend Build (`package.json` + `vite.config.js`)
- Scripts: `npm run build` → `vite build`; `npm run dev` → `vite`.
- Inputs: `resources/css/app.css`, `resources/css/filament/admin/theme.css`, `resources/js/app.js`.
- Uses `laravel-vite-plugin` with `refresh: true` and Tailwind CSS v4 via `@tailwindcss/vite`.
- Dev server runs on port `5174` with `strictPort: true`, ignoring compiled Blade views.

### Composer Scripts (`composer.json`)
- `setup`: full bootstrap (install deps, copy `.env.example`, keygen, migrate, npm install+build).
- `dev`: disables process timeout and runs `artisan dev`.
- `test`: clears config then runs `artisan test`.
- `post-autoload-dump`: package discovery + `filament:upgrade`.
- `post-update-cmd`: publishes Laravel assets.
- `post-root-package-install` / `post-create-project-cmd`: env/keygen/migrate scaffolding.

### Test Configuration (`phpunit.xml`)
- Two suites: `Unit` (`tests/Unit`) and `Feature` (`tests/Feature`).
- Source coverage includes `app/`.
- Hardcoded test environment: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`.

### Key Conventions Observed
- Production images are multi-stage and never contain dev dependencies or source trees beyond what's needed at runtime.
- Frontend assets are built in a separate Node stage and copied into the final image — the runtime image does not need Node.
- Development uses bind-mounted source volumes so hot-reload works; production uses prebuilt images pulled from GHCR.
- Database migrations are controlled via the `RUN_MIGRATIONS` env var in production compose.
- CI enforces both code style (`pint --test`) and passing tests before any image is built or pushed.
- Image tagging follows semantic tags: `latest` for master branch, short SHA, and Git tag refs.