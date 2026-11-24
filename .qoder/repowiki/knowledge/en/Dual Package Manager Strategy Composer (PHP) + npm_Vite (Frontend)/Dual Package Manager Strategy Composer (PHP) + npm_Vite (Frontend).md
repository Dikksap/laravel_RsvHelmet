---
kind: dependency_management
name: 'Dual Package Manager Strategy: Composer (PHP) + npm/Vite (Frontend)'
category: dependency_management
scope:
    - '**'
source_files:
    - composer.json
    - composer.lock
    - package.json
    - package-lock.json
    - .npmrc
    - Dockerfile.prod
---

## Approach

The RSV Helmet Laravel application uses two independent package managers:

- **Composer** for PHP dependencies (Laravel framework, Filament admin panel, PDF generation, testing tools).
- **npm** for frontend build tooling (Vite, Tailwind CSS v4, Laravel Vite plugin). Frontend assets are compiled into `public/build` and committed as static files.

There is no vendoring of JS libraries; Node modules are installed fresh at build time via `npm ci`. There is no private registry configuration — both registries are the public defaults (`packagist.org`, `registry.npmjs.org`).

## Key Files

- `composer.json` — declares PHP runtime and dev dependencies, PSR-4 autoload mappings, Composer scripts, and stability settings.
- `composer.lock` — pinned lockfile (copied into the Composer builder stage in Docker).
- `package.json` — declares only frontend build dependencies under `devDependencies`; no runtime JS packages.
- `package-lock.json` — npm lockfile used by `npm ci` in the frontend builder stage.
- `.npmrc` — sets `ignore-scripts=true` and `audit=true` globally for all npm operations.
- `Dockerfile.prod` — multi-stage build that installs Composer deps with `--no-dev --no-scripts --prefer-dist --optimize-autoloader`, then runs `npm ci` to install a deterministic frontend dependency tree.
- `.dockerignore` / `.gitignore` — determine what gets copied into containers and version control.

## Architecture & Conventions

### PHP Dependencies (`composer.json`)

- Runtime dependencies are declared under `require`: `laravel/framework ^13.17`, `filament/filament ^5.0`, `barryvdh/laravel-dompdf ^3.1`, `fibtegis/filament-infinite-scroll ^2.0`, `mrpunyapal/filament-infinite-select ^0.2`, plus `php ^8.3` and `laravel/tinker ^3.0`.
- Dev/test dependencies live under `require-dev`: `fakerphp/faker`, `laravel/boost`, `laravel/pail`, `laravel/pao`, `laravel/pint`, `mockery/mockery`, `nunomaduro/collision`, `phpunit/phpunit ^12.5.12`.
- Autoloading follows PSR-4: `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, `Tests\` → `tests/`.
- Stability policy: `minimum-stability: stable` with `prefer-stable: true` — pre-release versions are excluded unless explicitly required.
- Plugin allowlist in `config.allow-plugins` explicitly permits only `pestphp/pest-plugin` and `php-http/discovery`.
- Scripts orchestrate setup (`composer install` → `.env` copy → `key:generate` → `migrate --force` → `npm install --ignore-scripts` → `npm run build`), development (`@php artisan dev`), testing (`artisan config:clear && test`), and post-install hooks (`filament:upgrade`, vendor asset publishing).

### Frontend Dependencies (`package.json`)

- Only build-time dependencies are declared: `vite ^8.0.0`, `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`, `laravel-vite-plugin ^3.1`, `concurrently ^10.0.3`.
- `@laravel/multiplex ^0.4.1` is declared as an `optionalDependency` (not required for core builds).
- The project is marked `private: true` and `type: module`.
- Build outputs go to `public/build` (consumed by Blade views and Filament resources).

### Lockfiles & Determinism

- Both `composer.lock` and `package-lock.json` are present and used by their respective installers.
- Docker's production build uses `npm ci` (strict, lockfile-only install) rather than `npm install`, ensuring reproducible frontend builds.
- Composer install in Docker uses `--prefer-dist --optimize-autoloader --no-scripts` for fast, minimal installs.

### CI/CD Integration

- GitHub Actions workflow (`github/workflows/ci-cd.yml`) drives the build pipeline; it invokes the same Composer/npm commands defined in the manifests.
- No private repository credentials or custom registries are configured — all packages resolve from public sources.

## Conventions & Constraints

- **Rule:** PHP runtime and dev dependencies are separated into `require` vs `require-dev` in `composer.json`; production Docker images install with `--no-dev` so dev packages never enter the runtime image.
- **Rule:** Frontend dependencies are installed deterministically via `npm ci` using `package-lock.json` (enforced by `Dockerfile.prod`).
- **Rule:** All npm scripts run with `ignore-scripts=true` (enforced by `.npmrc`), preventing arbitrary code execution during install.
- **Rule:** Composer plugins are restricted to an explicit allowlist under `config.allow-plugins`; any new plugin must be added there before it can run.
- **Rule:** Minimum PHP version is locked to `^8.3` in `composer.json` and the runtime image targets `php:8.4-fpm-alpine` in `Dockerfile.prod`.
- **Convention:** New PHP packages follow semantic-version ranges with caret (`^`) constraints against major versions.
- **Convention:** New frontend packages are added only under `devDependencies` since they are build-only; runtime JS is not shipped as node modules.
- **Convention:** Composer scripts centralize bootstrap steps (`setup`, `dev`, `test`) so local and CI environments share the same initialization sequence.