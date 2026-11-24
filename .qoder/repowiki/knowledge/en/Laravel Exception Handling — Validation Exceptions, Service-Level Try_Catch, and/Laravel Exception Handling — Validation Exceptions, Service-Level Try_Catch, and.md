---
kind: error_handling
name: Laravel Exception Handling — Validation Exceptions, Service-Level Try/Catch, and Monolog Logging
category: error_handling
scope:
    - '**'
source_files:
    - config/logging.php
    - config/app.php
    - lang/id/validation.php
    - app/Filament/Resources/InventoryMovements/Pages/CreateInventoryMovement.php
    - app/Services/ExternalBarangApiService.php
    - app/Services/ExternalBarangSyncService.php
---

## Overview

RSV Helmet is a Laravel 13 application that does **not** define any custom exception classes (no `app/Exceptions` directory exists). Error handling is delegated to Laravel's built-in exception framework plus localized validation messages under `lang/id/validation.php`. The codebase follows two distinct patterns depending on layer:

- **Filament pages/resources**: catch `Illuminate\Validation\ValidationException`, re-prefix field keys via `prefixFormErrorKeys()`, then re-throw so Filament renders them as form-field errors.
- **Service layer** (`app/Services/*`): wrap HTTP calls in `try/catch (Exception $e)`, log via `Illuminate\Support\Facades\Log`, and return safe defaults (empty arrays / `null`) instead of propagating exceptions.

There is no global exception handler override (the default `App\Exceptions\Handler` is not present), so Laravel's default handler routes unhandled exceptions to the standard debug/production error pages controlled by `config('app.debug')`.

## Key Files

- `config/logging.php` — Monolog configuration; default channel is `stack` → `single`, writing to `storage/logs/laravel.log`; additional channels include `daily`, `slack`, `papertrail`, `stderr`, `syslog`, `errorlog`, `null`, and `emergency`.
- `config/app.php` — `APP_DEBUG` controls whether detailed stack traces are shown or a generic error page is rendered.
- `lang/id/validation.php` — Full Indonesian translation of Laravel's built-in validation rules plus an `attributes` map (e.g. `item_code` → `kode barang`).
- `app/Filament/Resources/InventoryMovements/Pages/CreateInventoryMovement.php` — canonical example of catching `ValidationException` and calling `$this->prefixFormErrorKeys($exception)` to scope field names to the current Filament form path.
- `app/Services/ExternalBarangApiService.php` — representative service-layer pattern: every public method wraps its HTTP call in `try/catch (Exception $e)`, logs with `Log::warning` / `Log::error`, and returns a typed empty/default shape.
- `app/Services/ExternalBarangSyncService.php` — similar pattern, also catches bare `Exception` (sometimes without binding the variable).
- `.claude/skills/laravel-best-practices/rules/error-handling.md` — documented convention file for the project's error-handling expectations.

## Architecture & Conventions Observed

### 1. Validation Errors Flow Upward

Validation is performed by Eloquent model rules / Form requests / Filament actions. When validation fails, Laravel throws `Illuminate\Validation\ValidationException`. In Filament create/update pages the exception is caught and re-thrown after mapping field keys through `prefixFormErrorKeys()`:

```php
catch (ValidationException $exception) {
    throw $this->prefixFormErrorKeys($exception);
}
```

This helper prepends the form's `statePath` to each error key so Filament can bind the message to the correct nested field.

### 2. External API Failures Are Swallowed

`ExternalBarangApiService` methods (`getBarangList`, `getBarangDetail`, `getRekapVariant`, `ping`) all follow the same shape:

1. Call `$this->client()->get(...)`.
2. If response is successful, return the JSON payload.
3. If response is unsuccessful, `Log::warning` with status/body and return a typed empty default.
4. If an `Exception` is thrown, `Log::error` with the message and return the same empty default.

This makes the external API effectively optional — callers receive `['data' => [], 'meta' => []]` when the remote service is down.

### 3. No Custom Exception Types

No domain-specific exception classes exist anywhere under `app/`. All business logic either:
- lets Laravel exceptions propagate (validation, model-not-found, etc.), or
- catches `Exception` broadly and converts it to a safe return value.

### 4. Logging Is Centralized Through Monolog

All logging goes through `Illuminate\Support\Facades\Log`. The default channel writes to `storage/logs/laravel.log` at `debug` level. Production deployments can switch to `daily`, `slack`, `papertrail`, or `stderr` via environment variables (`LOG_CHANNEL`, `LOG_LEVEL`, `LOG_STACK`). Deprecations are routed to `null` by default.

### 5. Presentation Layer Uses Filament Notifications

Filament resources/pages surface user-facing errors via Filament's built-in notification system (success/toast messages) rather than throwing exceptions from controllers. This is visible across Filament resource pages that perform CRUD operations.

### 6. Blade Views Do Not Handle Exceptions

Blade templates under `resources/views/` contain no `try/catch` blocks; they assume their parent controller/Livewire component has already validated input and produced valid data.

## Rules Enforced by the Codebase

- **Validation errors must be re-thrown as `ValidationException`** — Filament pages use `prefixFormErrorKeys()` to ensure field-scoped error messages render correctly against the current form state path.
- **External HTTP calls must not let exceptions escape the service boundary** — `ExternalBarangApiService` and `ExternalBarangSyncService` consistently wrap every HTTP call in `try/catch (Exception $e)` and return typed defaults, so callers never need to handle network failures.
- **User-visible messages are localized through `lang/id/validation.php`** — attribute names and rule messages are translated to Indonesian; English fallbacks are available via `APP_FALLBACK_LOCALE`.
- **Unhandled exceptions fall back to Laravel's default handler** — with `APP_DEBUG=false` (default in production), users see a generic error page; with `APP_DEBUG=true`, full stack traces are displayed.
- **Structured logging uses Monolog channels** — new error paths should use `Log::warning` / `Log::error` with contextual arrays rather than raw `echo`/`print`.