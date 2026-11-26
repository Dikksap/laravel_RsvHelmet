---
kind: dependency_management
name: Composer + npm Dual Dependency Management with Lockfiles and Multi-Stage Docker Builds
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

## Overview

This Laravel application manages dependencies through two independent package managers:

- **PHP side**: Composer (Packagist) — declared in `composer.json`, pinned by `composer.lock`.
- **Frontend side**: npm (npm registry) — declared in `package.json`, pinned by `package-lock.json`.

There is no vendoring of PHP or JS source code into the repository; all third-party packages are pulled from their public registries at install/build time. Private registries, mirrors, or `GOPRIVATE`-style configuration are not used.

## PHP Dependencies (Composer)

### Declaration

`composer.json` declares runtime dependencies under `require` and development-only dependencies under `require-dev`:

- Runtime: `laravel/framework ^13.17`, `filament/filament ^5.0`, `barryvdh/laravel-dompdf ^3.1`, `fibtegis/filament-infinite-scroll ^2.0`, `mrpunyapal/filament-infinite-select ^0.2`, `laravel/tinker ^3.0`.
- Dev: `phpunit/phpunit ^12.5.12`, `mockery/mockery ^1.6`, `fakerphp/faker ^1.23`, `laravel/pint ^1.27`, `nunomaduro/collision ^8.6`, `laravel/boost ^2.10`, `laravel/pail ^1.2.5`, `laravel/pao ^1.0.6`.

The project requires `php ^8.3` and sets `minimum-stability: stable` with `prefer-stable: true`, so only stable releases are accepted.

### Versioning strategy

All versions use caret (`^`) ranges against Packagist tags, allowing compatible minor/patch upgrades within the major version. The exact resolved tree is committed in `composer.lock` (a ~11,800-line file), which pins every transitive dependency to a specific version and commit SHA.

### Autoloading

PSR-4 mappings are configured for `App\`, `Database\Factories\`, `Database\Seeders\`, and `Tests\` (dev).

### Composer scripts

- `setup`: runs `composer install`, copies `.env.example`, generates the app key, runs migrations, then installs and builds frontend assets via npm.
- `post-autoload-dump`: runs Laravel's autoloader dump, discovers packages, and runs `filament:upgrade`.
- `post-update-cmd`: republishes Laravel assets after `composer update`.
- `post-root-package-install` / `post-create-project-cmd`: bootstrap steps for new installations.
- `pre-package-uninstall`: invokes Composer's pre-uninstall hook.

### Plugin policy

`config.allow-plugins` explicitly permits only `pestphp/pest-plugin` and `php-http/discovery`. All other Composer plugins are blocked by default — this is an explicit security constraint enforced by Composer itself.

### No private repositories

No `repositories` entries, no `COMPOSER_AUTH`, no custom packagist mirror, and no `vendor/` directory checked in. Dependencies come exclusively from Packagist.

## Frontend Dependencies (npm)

### Declaration

`package.json` is minimal and private (`"private": true`). It declares build-time tooling under `devDependencies`:

- `vite ^8.0.0`
- `@tailwindcss/vite ^4.0.0`
- `tailwindcss ^4.0.0`
- `laravel-vite-plugin ^3.1`
- `concurrently ^10.0.3`

An optional dependency `@laravel/multiplex ^0.4.1` is listed under `optionalDependencies`.

Scripts expose `build` (`vite build`) and `dev` (`vite`).

### Lockfile

`package-lock.json` is committed alongside `package.json`, pinning the full dependency tree to exact versions. The production Dockerfile uses `npm ci` (not `npm install`), which enforces strict lockfile-based installs and fails if `package-lock.json` drifts from `package.json`.

### npm configuration

`.npmrc` sets:
- `ignore-scripts=true` — prevents arbitrary native build scripts from running during install.
- `audit=true` — enables vulnerability auditing on install.

No custom registry URL or auth token is configured here.

## Build & Deployment Integration

`Dockerfile.prod` is a three-stage multi-stage build that ties both dependency systems together:

1. **Stage 1** (`composer:2`): Copies `composer.json` and `composer.lock`, then runs `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs`. The resulting `/app/vendor` is preserved for later stages.
2. **Stage 2** (`node:22-alpine`): Copies `package*.json`, runs `npm ci`, then copies the vendor directory from stage 1 (Filament theme build needs it) and runs `npm run build` to produce `public/build`.
3. **Stage 3** (`php:8.4-fpm-alpine`): Installs system packages and PHP extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`), then copies the application source plus the pre-built `vendor` and `public/build` directories.

The production image does not install Composer or Node at runtime — dependencies are baked in as static artifacts.

## Conventions Observed

- Both `composer.lock` and `package-lock.json` are committed to version control, making deployments reproducible without network access beyond the initial fetch.
- Development vs. production dependency separation is maintained on both sides (`require-dev` in Composer, `devDependencies` in npm).
- Composer plugin execution is restricted to an allowlist via `config.allow-plugins`.
- npm script execution is disabled globally via `.npmrc`'s `ignore-scripts=true`.
- Production Docker images use `--no-dev` for Composer and `npm ci` for npm, ensuring dev-only packages never leak into the runtime image.
- No vendored third-party PHP or JavaScript source is tracked in git; everything is fetched from public registries.