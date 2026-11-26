---
kind: error_handling
name: Laravel Exception Pipeline with Filament-Specific Error Types
category: error_handling
scope:
    - '**'
source_files:
    - bootstrap/app.php
    - app/Providers/AppServiceProvider.php
    - .claude/skills/laravel-best-practices/rules/error-handling.md
    - app/Filament/Imports/ProductStockImporter.php
    - app/Filament/Imports/ProductionBatchItemImporter.php
    - app/Filament/Imports/StockOpnameLineImporter.php
    - app/Filament/Pages/DaftarBarangEksternal.php
    - app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php
    - app/Filament/Resources/InventoryMovements/InventoryMovementResource.php
    - app/Filament/Resources/InventoryMovements/Pages/CreateInventoryMovement.php
    - app/Filament/Resources/ItemRequests/ItemRequestResource.php
---

## Approach

The application uses Laravel's built-in exception handling pipeline (`bootstrap/app.php` → `withExceptions`) and relies on framework-provided exceptions rather than defining custom domain exception classes. There is no `app/Exceptions` directory — no custom exception types, no `Handler.php`, no global `report()`/`render()` overrides.

## Central configuration

- **`bootstrap/app.php`** registers a single exception policy: JSON rendering is forced for routes matching `api/*` or requests that `expectsJson()`. No other exception handlers are registered (no `->report()`, `->render()`, `->shouldReport()`, `->dontReportDuplicates()`, or `->throttle()` calls).
- **`AppServiceProvider::boot()`** contains no exception-related code; it only configures Filament table date formats.

## Exceptions used in practice

| Exception | Source | Where it is thrown / caught |
|---|---|---|
| `Illuminate\Validation\ValidationException` | Framework | Caught in Filament resources/pages to convert validation failures into Filament notifications before rethrowing or halting processing (`InventoryBalanceResource`, `InventoryMovementResource`, `CreateInventoryMovement`, `ItemRequestResource`, `StockOpnameService`, `ExternalBarangSyncService`, `ItemRequestService`, `StockMovementService`). |
| `Filament\Support\Exceptions\Halt` | Filament support library | Used to abort Filament action pipelines after showing a notification (e.g. `DaftarBarangEksternal`, `InventoryMovementResource`, `ViewStockOpname`). |
| `Filament\Actions\Imports\Exceptions\RowImportFailedException` | Filament Imports package | Thrown from every importer (`ProductStockImporter`, `ProductionBatchItemImporter`, `StockOpnameLineImporter`) inside `resolveRecord()` to mark individual CSV rows as failed while letting the rest of the import continue. |

## Import error pattern

Each importer follows the same shape:
1. Validate mapped columns in `getColumns()` via `rules([...])`.
2. In `resolveRecord()`, throw `RowImportFailedException` with a user-facing Indonesian message when business rules fail (missing parent item, missing size attribute, unknown variant, missing stock record, invalid batch ID, etc.).
3. The Filament import pipeline catches these per-row and reports them through its own UI; successful rows proceed to `saveRecord()`.
4. `getCompletedNotificationBody()` summarizes success/failure counts.
5. Imports run synchronously (`getJobConnection()` returns `'sync'`) so they work without a queue worker.

## Validation flow in Filament resources

Resources catch `ValidationException` around save/update operations, extract `$exception->validator->errors()->all()`, flash it via `filament()->notify()` with an `error` level, then either `throw $exception` (to let Filament render the form) or `throw new Halt` (to stop further processing). This keeps validation errors visible in the Filament UI rather than surfacing raw HTTP responses.

## Conventions observed

- No custom exception hierarchy exists under `app/Exceptions`; all domain errors are surfaced as framework or Filament exceptions.
- Business-rule violations in imports use `RowImportFailedException` with localized Indonesian messages rather than generic `RuntimeException`s.
- Filament action pipelines are aborted with `Halt` after displaying a notification.
- API JSON response behavior is centralized in `bootstrap/app.php` via `shouldRenderJsonWhen()`.
- There is no centralized logging/reporting hook (no `report()` callbacks), no duplicate-report suppression, no throttling, and no `ShouldntReport` usage.
- The `.claude/skills/laravel-best-practices/rules/error-handling.md` file documents recommended patterns (custom exception classes with `report()`/`render()`, `ShouldntReport`, `throttle()`, `context()`, `dontReportDuplicates()`) but none of those patterns are implemented in the application code — they serve as guidance for future additions.