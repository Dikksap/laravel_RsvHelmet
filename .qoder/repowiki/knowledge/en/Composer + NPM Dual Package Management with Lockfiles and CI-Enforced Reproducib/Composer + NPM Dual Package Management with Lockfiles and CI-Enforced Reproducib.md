---
kind: dependency_management
name: Composer + NPM Dual Package Management with Lockfiles and CI-Enforced Reproducibility
category: dependency_management
scope:
    - '**'
source_files:
    - composer.json
    - composer.lock
    - package.json
    - package-lock.json
    - .npmrc
    - .github/workflows/ci-cd.yml
    - Dockerfile.prod
---

## System Overview

This Laravel application manages dependencies through two independent package managers:

- **PHP/Composer** for backend packages (Laravel framework, Filament admin panel, PDF generation, etc.)
- **Node.js/NPM** for frontend build tooling (Vite, Tailwind CSS, Laravel Vite plugin)

Both ecosystems use lockfiles (`composer.lock`, `package-lock.json`) to pin exact transitive dependency versions, ensuring reproducible builds across development, CI, and production.

## Key Files

- `composer.json` — PHP dependency manifest, autoload config, Composer scripts, and plugin allowlist
- `composer.lock` — Deterministic lockfile for all PHP dependencies (11,800+ lines)
- `package.json` — Frontend devDependencies and optionalDependencies
- `package-lock.json` — Deterministic lockfile for Node dependencies (2,317 lines)
- `.npmrc` — NPM runtime configuration
- `.github/workflows/ci-cd.yml` — CI pipeline that installs both dependency sets
- `Dockerfile.prod` — Multi-stage Docker build that copies `composer.lock` and uses `npm ci`

## Architecture & Conventions

### PHP Dependencies (Composer)

- Runtime dependencies declared under `require`: `laravel/framework ^13.17`, `filament/filament ^5.0`, `barryvdh/laravel-dompdf ^3.1`, `fibtegis/filament-infinite-scroll ^2.0`, `mrpunyapal/filament-infinite-select ^0.2`, plus `php ^8.3`.
- Development-only dependencies under `require-dev`: testing (`phpunit/phpunit ^12.5.12`, `mockery/mockery ^1.6`, `nunomaduro/collision ^8.6`), code quality (`laravel/pint ^1.27`), IDE helpers (`laravel/pail ^1.2.5`, `laravel/pao ^1.0.6`), and `fakerphp/faker ^1.23`.
- Autoloading follows PSR-4: `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, `Tests\` → `tests/`.
- Composer scripts automate setup: `setup` runs install, env copy, key generation, migration, npm install/build; `dev` starts the Laravel dev server; `test` clears config then runs PHPUnit.
- Post-install hooks run `artisan package:discover` and `artisan filament:upgrade`; post-update publishes vendor assets via `vendor:publish --tag=laravel-assets`.
- Plugin security is gated via `config.allow-plugins`: only `pestphp/pest-plugin` and `php-http/discovery` are whitelisted.
- Stability policy: `minimum-stability: stable` with `prefer-stable: true`.
- No private repositories or custom registries are configured in `composer.json`.

### Frontend Dependencies (NPM)

- DevDependencies: `vite ^8.0.0`, `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`, `laravel-vite-plugin ^3.1`, `concurrently ^10.0.3`.
- Optional dependency: `@laravel/multiplex ^0.4.1` (only installed on Linux/macOS with Node >= 22.13.0).
- Scripts: `build` runs `vite build`, `dev` runs `vite`.
- `.npmrc` sets `ignore-scripts=true` globally and enables `audit=true`.

### Build & Deployment Integration

- The GitHub Actions CI (`ci-cd.yml`) caches Composer dependencies keyed by `composer.lock` hash and caches NPM via `actions/setup-node`'s built-in cache.
- Production Docker build (`Dockerfile.prod`) is multi-stage:
  - Stage 1 (`composer:2`): installs PHP deps with `--no-dev --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs` using only `composer.json` and `composer.lock`.
  - Stage 2 (`node:22-alpine`): runs `npm ci` (lockfile-only install) then builds assets; requires the `vendor` directory from stage 1 because Filament's theme build depends on published vendor files.
  - Stage 3 (`php:8.4-fpm-alpine`): copies compiled assets and vendor dir into a minimal runtime image.
- CI runs `composer install --prefer-dist --no-interaction --no-progress` and `npm ci` before tests, enforcing lockfile usage.
- Deployment pulls the prebuilt image from GHCR (`ghcr.io/dikksap/laravel_rsvhelmet`) and runs `php artisan config:cache`, `route:cache`, `view:cache` after compose brings up the stack.

### Vendor Strategy

- No vendored third-party source code is committed beyond what Composer/NPM place under `vendor/` and `node_modules/` (which are gitignored). The repo relies entirely on remote registries (Packagist for PHP, npm registry for JS).
- Filament's generated frontend assets (CSS/JS/fonts) are committed under `public/css/filament/`, `public/js/filament/`, and `public/fonts/filament/` as pre-built artifacts rather than rebuilt at deploy time.

## Conventions & Constraints

- **Lockfiles are authoritative**: Both `composer.lock` and `package-lock.json` are committed and used by CI (`npm ci`) and Docker builds to ensure deterministic dependency resolution.
- **PHP version pinned at 8.3+** in `composer.json` and **Node 22** in CI and Dockerfile, keeping runtime environments aligned.
- **No private registries or Composer mirrors** are configured; all packages resolve from Packagist and the public npm registry.
- **Plugin execution is restricted**: Composer's `allow-plugins` config explicitly permits only `pestphp/pest-plugin` and `php-http/discovery`, blocking arbitrary package scripts by default.
- **Dev vs prod separation**: `composer install --no-dev` in production Docker builds excludes test/dev dependencies; NPM `optionalDependencies` isolates platform-specific tools.
- **CI enforces style and tests**: `vendor/bin/pint --test` runs before `php artisan test`, making code quality checks part of the dependency-driven build pipeline.