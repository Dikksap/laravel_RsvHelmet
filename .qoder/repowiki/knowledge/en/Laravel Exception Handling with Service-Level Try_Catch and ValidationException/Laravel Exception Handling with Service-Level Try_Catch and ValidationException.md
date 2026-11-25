---
kind: error_handling
name: Laravel Exception Handling with Service-Level Try/Catch and ValidationException
category: error_handling
scope:
    - '**'
source_files:
    - bootstrap/app.php
    - .claude/skills/laravel-best-practices/rules/error-handling.md
    - app/Services/ExternalBarangApiService.php
    - app/Services/ExternalBarangSyncService.php
---

## Approach

The application uses Laravel's built-in exception system without defining any custom exception classes. There is no `app/Exceptions` directory, no domain-specific exception types, and no sentinel error codes. Error handling is split between two layers:

1. **Global exception policy** — centralized in `bootstrap/app.php` via `withExceptions`, which only configures JSON rendering for API routes.
2. **Service-level defensive try/catch** — individual service methods wrap HTTP calls and database operations in `try { ... } catch (Exception $e)` blocks that log the error and return a safe default value instead of propagating it.

## Global configuration

`bootstrap/app.php` registers a single exception callback:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

This is the only place where the framework's exception pipeline is customized. No `report()` or `render()` callbacks are registered, no `ShouldntReport` exceptions are used, no `throttle()` / `dontReportDuplicates()` configuration appears, and no custom error views are defined under `resources/views/errors`. All unhandled exceptions fall through to Laravel's default handler, which renders the stock `errors::minimal` Blade view (visible in compiled views under `storage/framework/views`).

## Business validation errors

Validation failures are surfaced by throwing `Illuminate\Validation\ValidationException` with `ValidationException::withMessages([...])`. This pattern is used in `app/Services/ExternalBarangSyncService.php` to signal invalid input from callers (Filament pages or other services):

- `syncVariantOutput` throws when no eligible units exist or the requested quantity is zero.
- `syncBatchOutput` throws when the batch code is empty or no groups/unmapped items are found.

These exceptions propagate up to Filament resources, which convert them into form validation feedback on the admin UI.

## Defensive service-layer error handling

`app/Services/ExternalBarangApiService.php` wraps every external HTTP call in a `try/catch (Exception $e)` block. On failure it:

- Logs the error via `Log::error(...)` with contextual fields (`kodeBarang`, `error`, `status`, `body`).
- Returns a safe default: an empty array like `['data' => [], 'meta' => []]` or `null`.

Example pattern (repeated across `getBarangList`, `getBarangDetail`, `getRekapVariant`, `ping`):

```php
try {
    $response = $this->client()->get('/api/public/barang', $params);
    if ($response->successful()) {
        return $response->json();
    }
    Log::warning('ExternalBarangApiService: Gagal mengambil daftar barang', [...]);
    return ['data' => [], 'meta' => []];
} catch (Exception $e) {
    Log::error('ExternalBarangApiService: Error koneksi ke API barang', [
        'error' => $e->getMessage(),
    ]);
    return ['data' => [], 'meta' => []];
}
```

`app/Services/ExternalBarangSyncService.php` follows the same pattern around per-unit DB writes inside a loop: each failing unit is logged and skipped while the rest of the batch continues processing, so one bad record does not abort the entire sync.

## Conventions observed

- No custom exception classes are defined anywhere in the codebase; all thrown errors are either `ValidationException` or the base `Exception` caught at the service boundary.
- External integrations are treated as unreliable: network failures are swallowed and converted to defaults rather than propagated upward.
- Business-rule violations (invalid parameters, missing data) use `ValidationException::withMessages` so they surface as field-level validation errors in Filament.
- Logging uses `Log::warning` for non-fatal API response issues and `Log::error` for connection/runtime failures, both with structured context arrays.
- The global exception handler only controls JSON content negotiation; there is no centralized reporting sink, no custom error pages, and no `ShouldntReport` usage.
- The author guidelines in `.claude/skills/laravel-best-practices/rules/error-handling.md` prescribe using exception-specific `report()`/`render()` methods and `withExceptions` callbacks, but the current codebase does not follow the custom-class part of that guidance — it relies entirely on the framework defaults plus service-level try/catch.