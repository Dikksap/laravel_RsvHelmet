---
kind: error_handling
name: Laravel Exception Handling with Filament-Specific Exceptions
category: error_handling
scope:
    - '**'
source_files:
    - bootstrap/app.php
    - app/Filament/Imports/ProductStockImporter.php
    - app/Filament/Imports/ProductionBatchItemImporter.php
    - app/Filament/Imports/StockOpnameLineImporter.php
    - app/Filament/Resources/InventoryMovements/InventoryMovementResource.php
    - app/Filament/Resources/StockOpnames/Pages/ViewStockOpname.php
    - database/seeders/MasterItemSeeder.php
    - .claude/skills/laravel-best-practices/rules/error-handling.md
---

## Approach

The application follows Laravel's standard exception-handling model with no custom `app/Exceptions` directory or domain-specific exception classes. Error propagation is done by throwing PHP exceptions directly from business logic, import validators, and Filament resources. There is no middleware-based error handling layer; instead, the single centralized configuration lives in `bootstrap/app.php`.

## Centralized Configuration

`bootstrap/app.php` registers only one exception policy:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
});
```

This makes API routes under `api/*` and any request that expects JSON render exceptions as JSON responses; all other requests fall back to Laravel's default HTML rendering. No custom `report()` callbacks, no `dontReportDuplicates()`, no `throttle()` usage — the handler uses Laravel defaults for reporting.

## Thrown Exceptions

Across the codebase, errors surface through three distinct mechanisms:

1. **Filament import validation** — All row-level validation failures in the four importers (`ProductStockImporter`, `ProductionBatchItemImporter`, `StockOpnameLineImporter`) throw `Filament\Actions\Imports\Exceptions\RowImportFailedException` with Indonesian user-facing messages (e.g. `'Kolom item dan size wajib diisi.'`, `'Barang induk [{$parentItemName}] tidak ditemukan.'`). This is the dominant error-propagation pattern in data-import flows.

2. **Filament action cancellation** — Two Filament pages/resources (`InventoryMovementResource::bulkAction`, `ViewStockOpname::viewAction`) throw `Filament\Support\Exceptions\Halt` to abort a bulk action or view operation when a prerequisite check fails (e.g. missing parent movement, stock opname not in draft status).

3. **Standard PHP exceptions** — `InvalidArgumentException` is thrown in `database/seeders/MasterItemSeeder.php` for an unknown item type code: `throw new InvalidArgumentException("Unknown item type for code [{$code}].")`. Other framework exceptions (e.g. `ModelNotFoundException`, `ValidationException`) propagate naturally from Eloquent/Laravel without interception.

There are no custom domain exception classes (no `AppException`, `BusinessException`, etc.), no sentinel error codes, and no `try/catch` blocks wrapping service calls — errors bubble up to Laravel's global handler.

## Conventions Observed

- Import-row validation errors use `RowImportFailedException` with localized Indonesian messages rather than returning failure arrays; this lets Filament's import UI display per-row errors inline.
- Filament bulk/view actions signal "cannot proceed" by throwing `Halt` rather than returning a flash message or null, which integrates with Filament's action lifecycle.
- The seeders use plain `InvalidArgumentException` for invalid input during seeding, relying on Laravel's default 500 response.
- No `ShouldntReport` implementation is used anywhere in the codebase; all exceptions are reported via Laravel's default logger.
- No `context()` enrichment is attached to exceptions; structured context is not added at the exception level.
- The project's own best-practices guide at `.claude/skills/laravel-best-practices/rules/error-handling.md` prescribes using exception-specific `report()`/`render()` methods and centralized `withExceptions` callbacks, but the current codebase does not follow the custom-exception-class part of that guidance — it relies on framework exceptions instead.

## Key Files

- `bootstrap/app.php` — sole exception-policy registration
- `app/Filament/Imports/ProductStockImporter.php` — throws `RowImportFailedException` for row validation
- `app/Filament/Imports/ProductionBatchItemImporter.php` — same pattern
- `app/Filament/Imports/StockOpnameLineImporter.php` — same pattern
- `app/Filament/Resources/InventoryMovements/InventoryMovementResource.php` — throws `Halt` to cancel bulk actions
- `app/Filament/Resources/StockOpnames/Pages/ViewStockOpname.php` — throws `Halt` to cancel view actions
- `database/seeders/MasterItemSeeder.php` — throws `InvalidArgumentException` for invalid seeder input
- `.claude/skills/laravel-best-practices/rules/error-handling.md` — documented best practices (not yet implemented)