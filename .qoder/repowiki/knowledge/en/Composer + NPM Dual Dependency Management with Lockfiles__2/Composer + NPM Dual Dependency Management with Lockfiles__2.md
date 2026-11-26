---
kind: dependency_management
name: Composer + NPM Dual Dependency Management with Lockfiles
category: dependency_management
scope:
    - '**'
source_files:
    - composer.json
    - composer.lock
    - package.json
    - package-lock.json
    - .npmrc
    - .dockerignore
---

## Systems Used

This Laravel + Filament application manages dependencies through two independent package managers:

- **PHP**: Composer (`composer.json` + `composer.lock`) for PHP packages.
- **JavaScript**: NPM (`package.json` + `package-lock.json`) for frontend tooling (Vite, Tailwind, Laravel Vite plugin).

There is no vendoring of third-party code into the repository — both `vendor/` and `node_modules/` are excluded from Docker builds via `.dockerignore`, so dependencies are installed at build/deploy time rather than committed.

## Key Files

- `composer.json` — declares PHP runtime deps, dev deps, autoload PSR-4 mappings, Composer scripts, and stability settings.
- `composer.lock` — pinned lockfile for reproducible installs.
- `package.json` — declares Node.js devDependencies and optionalDependencies.
- `package-lock.json` — pinned lockfile for Node dependencies.
- `.npmrc` — global NPM config: `ignore-scripts=true`, `audit=true`.
- `.dockerignore` — excludes `vendor`, `node_modules`, `tests`, and generated caches from container images.

## Architecture and Conventions

### PHP (Composer)

- Runtime dependencies live under `require`: `laravel/framework ^13.17`, `filament/filament ^5.0`, `barryvdh/laravel-dompdf ^3.1`, `fibtegis/filament-infinite-scroll ^2.0`, `mrpunyapal/filament-infinite-select ^0.2`, plus `laravel/tinker ^3.0`. All require `php ^8.3`.
- Development-only tools live under `require-dev`: `fakerphp/faker`, `laravel/boost`, `laravel/pail`, `laravel/pao`, `laravel/pint`, `mockery/mockery`, `nunomaduro/collision`, `phpunit/phpunit ^12.5.12`.
- Autoloading uses PSR-4: `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, and `Tests\` → `tests/` (autoload-dev).
- Stability policy: `minimum-stability: stable` with `prefer-stable: true`, preventing accidental installation of pre-release packages.
- Plugin allowlist in `config.allow-plugins` explicitly permits only `pestphp/pest-plugin` and `php-http/discovery`; all other Composer plugins must be approved before running.
- Composer scripts automate lifecycle tasks:
  - `setup` runs `composer install`, copies `.env.example` to `.env`, generates the app key, runs migrations, then `npm install --ignore-scripts` and `npm run build`.
  - `post-autoload-dump` triggers `Illuminate\Foundation\ComposerScripts::postAutoloadDump`, `artisan package:discover`, and `artisan filament:upgrade`.
  - `post-update-cmd` republishes Laravel assets via `artisan vendor:publish --tag=laravel-assets --force`.
  - `post-root-package-install` / `post-create-project-cmd` handle initial `.env` generation, SQLite DB creation, and migration.
  - `pre-package-uninstall` invokes Composer's built-in cleanup script.
- `preferred-install: dist` tells Composer to prefer distribution archives over source trees.
- `sort-packages: true` keeps dependency entries alphabetically ordered.

### JavaScript (NPM)

- Dependencies are declared as `devDependencies` only: `@tailwindcss/vite ^4.0.0`, `concurrently ^10.0.3`, `laravel-vite-plugin ^3.1`, `tailwindcss ^4.0.0`, `vite ^8.0.0`.
- `optionalDependencies` includes `@laravel/multiplex ^0.4.1` (used when available but not required).
- The project is marked `"private": true` and uses ESM (`"type": "module"`).
- Scripts expose `build` (Vite production build) and `dev` (Vite dev server).
- `.npmrc` sets `ignore-scripts=true` globally (prevents native addon postinstall hooks) and enables `audit=true` for vulnerability scanning.

### CI/CD Integration

The GitHub Actions workflow (`github/workflows/ci-cd.yml`) drives dependency resolution during CI, ensuring that both `composer install` and `npm ci` (or equivalent) run against the lockfiles.

## Constraints and Rules

- **Lockfiles are committed** (`composer.lock`, `package-lock.json`) — versions are pinned for reproducible builds; updating a dependency requires committing the updated lockfile.
- **No private registries or custom repositories** are configured in either manifest; all packages resolve from Packagist and the public npm registry.
- **Composer plugins are whitelisted** — any new Composer plugin must be added to `config.allow-plugins` in `composer.json` before it can run (enforced by Composer itself).
- **Node postinstall scripts are disabled globally** via `.npmrc` `ignore-scripts=true`, which also means `npm install --ignore-scripts` is used in the Composer `setup` script to avoid redundant script execution.
- **Runtime vs. dev separation** is enforced by Composer's `require` vs. `require-dev` sections, and by NPM's `devDependencies` vs. `optionalDependencies` distinction.
- **Vendor/node_modules are never shipped** — `.dockerignore` excludes both directories, so container images install dependencies fresh from the lockfiles.