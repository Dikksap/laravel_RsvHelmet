---
kind: external_dependency
name: Filament 5 admin panel framework
slug: filament
category: external_dependency
category_hints:
    - framework_behavior
scope:
    - '**'
---

### Filament
- Role: Admin panel and CRUD scaffolding for the RSV Helmet inventory / production system. Panel mounted at `/admin`.
- Integration: `App\Providers\Filament\AdminPanelProvider` registers resources/pages/widgets under `app/Filament/`; custom login page lives in `app/Filament/Auth/Login.php` with Blade partials under `resources/views/filament/partials/` (auth-brand-panel.blade.php, auth-lockup.blade.php) and split-screen theme in `resources/css/filament/admin/theme.css`.
- Build/deploy: assets are published to `public/css/filament`, `public/js/filament`, `public/fonts/filament` via `php artisan filament:upgrade` (run in post-autoload-dump and documented in README); `filament:optimize` caches components & icons for production.
- CI runs `vendor/bin/pint --test` against Filament-generated PHP; tests use SQLite in-memory DB.
- Related third-party packages: `fibtegis/filament-infinite-scroll` (^2.0) and `mrpunyapal/filament-infinite-select` (^0.2) extend Filament forms.