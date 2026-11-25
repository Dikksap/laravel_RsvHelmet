---
kind: dependency_management
name: Composer + NPM Dual Dependency Management with Lockfiles and Multi-Stage Docker Builds
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

The project uses two independent package managers for its dependency surface:

- **PHP/Composer** (`composer.json` + `composer.lock`) for PHP runtime, Laravel framework, Filament admin panel, PDF generation, and testing tooling.
- **Node/NPM** (`package.json` + `package-lock.json`) for frontend build tooling (Vite, Tailwind CSS v4, Laravel Vite plugin) and the optional Laravel Multiplex package.

Both lockfiles are committed to the repository, pinning exact transitive resolutions so builds are reproducible across environments. There is no vendoring of PHP or JS source code — dependencies are fetched from Packagist and the npm registry at install/build time.

## Key Files

- `composer.json` — declares PHP dependencies, autoload PSR-4 mappings, Composer scripts, and plugin allowlist.
- `composer.lock` — committed lockfile used by CI and production.
- `package.json` — declares Node devDependencies and one optional dependency (`@laravel/multiplex`).
- `package-lock.json` — committed lockfile for deterministic Node installs.
- `.npmrc` — sets `ignore-scripts=true` and `audit=true` globally for npm operations.
- `Dockerfile.prod` — multi-stage build that installs both dependency sets in isolated stages.

## Architecture and Conventions

### PHP / Composer

- **Version ranges**: All packages use caret ranges (`^x.y`), allowing compatible updates while staying within major versions. The root `minimum-stability` is `stable` with `prefer-stable: true`, so only stable releases are resolved unless explicitly overridden.
- **Plugin security**: Only `pestphp/pest-plugin` and `php-http/discovery` are whitelisted via `config.allow-plugins`; all other Composer plugins are blocked by default.
- **Autoloading**: PSR-4 maps `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, and `Tests\` → `tests/` (under `autoload-dev`).
- **Scripts**:
  - `setup` runs a full bootstrap chain: `composer install` → copy `.env.example` → `key:generate` → `migrate --force` → `npm install --ignore-scripts` → `npm run build`.
  - `post-autoload-dump` triggers `artisan package:discover` and `artisan filament:upgrade` so Filament assets and service providers are registered automatically after every install/update.
  - `post-update-cmd` re-publishes Laravel assets (`vendor:publish --tag=laravel-assets --force`) to keep compiled assets in sync after Composer updates.
  - `pre-package-uninstall` invokes Composer's built-in cleanup script.
- **Optimization flags**: `optimize-autoloader: true`, `preferred-install: "dist"`, `sort-packages: true` are set under `config`.

### Node / NPM

- **Dev-only dependencies**: Frontend tooling lives under `devDependencies` (Vite, Tailwind CSS v4, `@tailwindcss/vite`, `laravel-vite-plugin`, `concurrently`). No runtime JS dependencies are declared.
- **Optional dependency**: `@laravel/multiplex` is listed under `optionalDependencies`, meaning it is installed if available but does not break the build if missing.
- **NPM safety**: `.npmrc` enforces `ignore-scripts=true` (no arbitrary postinstall hooks run during install) and `audit=true` (security advisories are reported).
- **Build integration**: The `build` script runs `vite build`; the `dev` script runs `vite`. The Composer `setup` script calls `npm install --ignore-scripts` followed by `npm run build`.

### Docker Build Strategy

`Dockerfile.prod` uses three distinct stages to keep the final image lean:

1. **`composer-builder`** (based on `composer:2`): Copies only `composer.json` and `composer.lock`, then runs `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs`. This produces a minimal `vendor/` directory without dev packages or executable scripts.
2. **`frontend-builder`** (based on `node:22-alpine`): Runs `npm ci` (from lockfile), copies the previously built `vendor/` (Filament theme needs vendor CSS/views to compile), then runs `npm run build` to emit `public/build`.
3. **`production`** (based on `php:8.4-fpm-alpine`): Installs system deps and PHP extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `opcache`, `pcntl`), then copies the application source plus the prebuilt `vendor/` and `public/build` artifacts from earlier stages.

This means the production image contains no Composer or Node toolchains — only the resolved artifacts.

## Conventions and Constraints

- **Lockfiles are authoritative**: Both `composer.lock` and `package-lock.json` are committed; production Docker uses `composer install` (not `update`) and `npm ci`, which fail if the lockfile is out of sync with the manifests.
- **No private registries configured**: Neither `composer.json` nor `.npmrc` defines custom repositories or authentication tokens; dependencies come from the default Packagist and npm registries.
- **PHP version constraint**: `composer.json` pins `php: ^8.3`; the production Docker image runs PHP 8.4 FPM.
- **Stability policy**: Composer is restricted to stable releases (`minimum-stability: stable`, `prefer-stable: true`).
- **Plugin allowlist is explicit**: Only `pestphp/pest-plugin` and `php-http/discovery` are permitted to execute Composer plugins; any new plugin must be added to `config.allow-plugins`.
- **Frontend scripts are disabled during install**: `.npmrc` sets `ignore-scripts=true`, and the Composer `setup` script passes `--ignore-scripts` to `npm install`, preventing arbitrary code execution from third-party package postinstall hooks.
- **Asset publishing is automated**: Filament assets and Laravel assets are republished automatically via Composer hooks (`post-autoload-dump` and `post-update-cmd`), so developers do not need to manually run `php artisan vendor:publish` after updating dependencies.