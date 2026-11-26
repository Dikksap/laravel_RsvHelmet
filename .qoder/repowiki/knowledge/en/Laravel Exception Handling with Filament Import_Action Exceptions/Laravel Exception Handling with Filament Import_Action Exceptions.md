---
kind: error_handling
name: Laravel Exception Handling with Filament Import/Action Exceptions
category: error_handling
scope:
    - '**'
source_files:
    - bootstrap/app.php
    - .claude/skills/laravel-best-practices/rules/error-handling.md
    - app/Filament/Imports/ProductStockImporter.php
    - app/Filament/Imports/ProductionBatchItemImporter.php
    - app/Filament/Imports/StockOpnameLineImporter.php
    - app/Filament/Resources/InventoryMovements/InventoryMovementResource.php
    - app/Filament/Resources/StockOpnames/Pages/ViewStockOpname.php
    - database/seeders/MasterItemSeeder.php
---

## System / Approach

The application follows the standard Laravel exception model — no custom `app/Exceptions` directory exists, and there are no domain-specific exception classes defined in this repository. Error handling is delegated to:

1. **Laravel's default exception handler** configured in `bootstrap/app.php`, which only customizes JSON rendering for API routes.
2. **Filament framework exceptions** (`RowImportFailedException`, `Halt`) used by importers and admin pages.
3. **PHP built-in exceptions** (`InvalidArgumentException`) used sparingly in seeders.
4. A documented best-practices guide at `.claude/skills/laravel-best-practices/rules/error-handling.md` that prescribes how custom exceptions *should* be written (exception methods, centralized callbacks, `ShouldntReport`, `context()`, `throttle()`, `dontReportDuplicates()`).

There is no global middleware error handler, no sentinel error codes, no panic/recover pattern (PHP has neither), and no custom logger or error-tracking integration beyond Laravel's default logging stack.

## Key Files

- `bootstrap/app.php` — central exception configuration via `withExceptions`.
- `.claude/skills/laravel-best-practices/rules/error-handling.md` — project-wide conventions for future custom exceptions.
- `app/Filament/Imports/ProductStockImporter.php` — uses `Filament\Actions\Imports\Exceptions\RowImportFailedException` for per-row validation failures during CSV import.
- `app/Filament/Imports/ProductionBatchItemImporter.php` — same `RowImportFailedException` pattern.
- `app/Filament/Imports/StockOpnameLineImporter.php` — same `RowImportFailedException` pattern.
- `app/Filament/Resources/InventoryMovements/InventoryMovementResource.php` — throws `Filament\Support\Exceptions\Halt` to abort a Livewire action.
- `app/Filament/Resources/StockOpnames/Pages/ViewStockOpname.php` — throws `Halt` from two action handlers.
- `database/seeders/MasterItemSeeder.php` — throws `InvalidArgumentException` for unknown item type codes.

## Architecture and Conventions

### Centralized exception policy
`bootstrap/app.php` registers a single `withExceptions` callback that sets:

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
);
```

This makes all unhandled exceptions render as JSON when the request path matches `api/*` or the request expects JSON; otherwise Laravel's default HTML error page is used. No other report/render callbacks are registered, so the default handler does all reporting/logging.

### Filament import errors
All three importers (`ProductStockImporter`, `ProductionBatchItemImporter`, `StockOpnameLineImporter`) validate row data inside `resolveRecord()` and throw `RowImportFailedException` with Indonesian-language messages (e.g. `'Kolom item dan size wajib diisi.'`, `'Barang induk [{$parentItemName}] tidak ditemukan.'`). The importer base class catches these and records them as failed rows; `getCompletedNotificationBody()` reports both successful and failed row counts.

### Filament action aborts
Admin actions use `Filament\Support\Exceptions\Halt` (imported as `use Filament\Support\Exceptions\Halt;`) to signal an unrecoverable condition within a Filament action handler. It is thrown in `InventoryMovementResource::bulkDelete()` and in `ViewStockOpname::markAsDone()` / `ViewStockOpname::cancel()`.

### Seeder validation
`MasterItemSeeder` uses PHP's native `InvalidArgumentException` in a `default` branch of a switch over item-type codes: `throw new InvalidArgumentException("Unknown item type for code [{$code}].")`.

### Best-practices guide (prescriptive)
The file `.claude/skills/laravel-best-practices/rules/error-handling.md` documents the intended pattern for any future custom exceptions:

- Define exception-specific `report()` and `render(Request $request)` methods on the exception class itself.
- Alternatively register centralized callbacks in `bootstrap/app.php` via `$exceptions->report(...)` / `$exceptions->render(...)`.
- Use `ShouldntReport` (or `dontReport()`) to suppress default reporting.
- Use `throttle()` with `Lottery`/`Limit` for high-volume exceptions.
- Enable `dontReportDuplicates()` to deduplicate identical exception objects across multiple `report()` calls.
- For APIs, override `shouldRenderJsonWhen` to force JSON regardless of `Accept` headers.
- Attach structured metadata via `context(): array` on the exception class.

These rules are stated as best practices in the skill document; they are not enforced by linting or CI in this repository.

## Conventions and Constraints

- **No custom exception classes exist** under `app/Exceptions`; the directory is absent. All runtime errors currently surface as either Filament exceptions, PHP built-ins, or Laravel's default handler.
- **Import validation errors** are expressed exclusively through `Filament\Actions\Imports\Exceptions\RowImportFailedException` with user-facing Indonesian messages; business logic inside `resolveRecord()` throws rather than returning error indicators.
- **Filament action aborts** use `Filament\Support\Exceptions\Halt` instead of throwing generic exceptions.
- **API responses** are rendered as JSON automatically when the route prefix is `api/*` or the request expects JSON, as configured in `bootstrap/app.php`.
- **Error messages** in importers are written in Indonesian (the app's locale), e.g. `'Kode barang wajib diisi.'`, `'Dokumen stok opname sudah tidak berstatus draft.'`.
- **Logging/reporting**: no custom reporter is wired up; all exceptions flow through Laravel's default logger (configured via `config/logging.php`), writing to `storage/logs/laravel.log`.
- **No try/catch blocks** were found in the scanned source files; error propagation relies on exception bubbling to the framework handler rather than local recovery.