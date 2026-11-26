---
kind: error_handling
name: Laravel Exception & Logging Strategy for External API Sync
category: error_handling
scope:
    - '**'
source_files:
    - app/Services/ExternalBarangApiService.php
    - app/Services/ExternalBarangSyncService.php
    - app/Filament/Pages/DaftarBarangEksternal.php
    - bootstrap/app.php
    - .claude/skills/laravel-best-practices/rules/error-handling.md
---

## Approach

This Laravel application does not define custom exception classes. Error handling is built on three layers:

1. **Fail-safe service methods** — `ExternalBarangApiService` wraps every HTTP call in `try/catch (Exception)` and returns a safe default (`['data' => [], 'meta' => []]`, `null`, or `false`) rather than propagating exceptions upward.
2. **Validation exceptions** — business-rule violations are raised as `Illuminate\Validation\ValidationException::withMessages([...])` from services and Filament resources, which the framework renders as 422 responses with field-specific messages.
3. **Structured logging** — failures that are swallowed at the service layer are recorded via `Illuminate\Support\Facades\Log::error` / `Log::warning` with contextual arrays keyed by domain fields (`kodeBarang`, `status`, `error`).

There is no `app/Exceptions` directory; all error types live inline in the code that throws them.

## Key files

- `app/Services/ExternalBarangApiService.php` — external HTTP client; catches `Exception` around every `Http::get()` call and logs via `Log::warning` / `Log::error`, returning empty defaults.
- `app/Services/ExternalBarangSyncService.php` — orchestrates sync; uses `DB::transaction()` per unit and per batch, catches `Exception` inside the transaction loop to log and continue processing remaining units, and throws `ValidationException::withMessages` for invalid input / missing mappings.
- `app/Filament/Pages/DaftarBarangEksternal.php` — Filament page action that catches `ValidationException` from the sync service, converts it to a persistent `Notification::make()->danger()`, then re-throws `Filament\Actions\Halt` to stop further action execution.
- `bootstrap/app.php` — global exception configuration: only `shouldRenderJsonWhen` is set so `api/*` routes and JSON-expecting requests render exceptions as JSON.
- `.claude/skills/laravel-best-practices/rules/error-handling.md` — documented best practices recommending custom exception classes with `report()`/`render()`, `ShouldntReport`, `throttle()`, `dontReportDuplicates()`, and `context()` — these patterns are described but not implemented in the current codebase.

## Architecture and conventions

### Fail-first API calls
Every public method of `ExternalBarangApiService` follows the same shape:

```php
try {
    $response = $this->client()->get(...);
    if ($response->successful()) return $response->json();
    Log::warning('...', ['status' => $response->status(), 'body' => $response->body()]);
    return ['data' => [], 'meta' => []];
} catch (Exception $e) {
    Log::error('...', ['error' => $e->getMessage()]);
    return ['data' => [], 'meta' => []];
}
```

The `ping()` method is the only one that returns `bool` instead of an array; it catches `Exception` silently and returns `false`.

### Per-item failure isolation during bulk sync
`ExternalBarangSyncService::sync()` iterates over paginated results from the external API. For each unit it opens a `DB::transaction()` wrapping the stock movement + idempotency record. If any step fails, the `catch (Exception $e)` block logs the error and the loop continues to the next unit — individual unit failures do not abort the entire sync run.

### Validation vs. runtime errors
- **Business-rule violations** (missing mapping, empty batch code, zero quantity, unknown production batch) throw `ValidationException::withMessages([...])`. These carry field names as keys and are surfaced to the caller as validation errors.
- **Runtime/integration failures** (HTTP timeout, malformed date, DB write failure) are caught and logged; they do not propagate because the calling code expects partial success in long-running operations.

### Filament presentation layer
In `DaftarBarangEksternal.php`, the sync action wraps the service call in `try/catch (ValidationException $exception)`, extracts the first message via `collect($exception->errors())->flatten()->first()`, shows a persistent danger notification, and re-throws `new Halt` to prevent subsequent actions from running.

### Global exception policy
`bootstrap/app.php` configures only JSON rendering for API routes:

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
);
```

No custom `report()` or `render()` callbacks are registered, so Laravel's default handler processes all other exceptions.

## Conventions and constraints

- External HTTP calls are never allowed to bubble up as uncaught exceptions; they are wrapped in `try/catch (Exception)` and converted to safe defaults. This is enforced by the uniform structure of every method in `ExternalBarangApiService`.
- Business validation failures use `ValidationException::withMessages([...])` with field-name keys, allowing the Filament UI to display per-field error messages. This pattern is used consistently across `ExternalBarangSyncService`, `ItemRequestService`, `StockOpnameService`, and Filament resource pages.
- Long-running bulk operations isolate per-record failures with `DB::transaction()` plus an inner `catch (Exception)` that logs and continues — individual bad records do not abort the whole batch.
- All logged errors include structured context arrays (e.g. `'kodeBarang'`, `'status'`, `'error'`) rather than plain strings, making them queryable in Laravel's log channels.
- The project's skill document recommends custom exception classes with `report()`/`render()`, `ShouldntReport`, `throttle()`, `dontReportDuplicates()`, and `context()` — these are presented as best practices but are not yet implemented in the codebase.
- No `panic`/`recover` equivalent exists (not applicable to PHP); there is no top-level `set_exception_handler` or global try/catch wrapper beyond Laravel's default exception handler.