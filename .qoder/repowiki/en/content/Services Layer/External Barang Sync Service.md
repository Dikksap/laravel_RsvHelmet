# External Barang Sync Service

<cite>
**Referenced Files in This Document**
- [ExternalBarangSyncService.php](file://app/Services/ExternalBarangSyncService.php)
- [ExternalBarangApiService.php](file://app/Services/ExternalBarangApiService.php)
- [StockMovementService.php](file://app/Services/StockMovementService.php)
- [ExternalItemMapping.php](file://app/Models/ExternalItemMapping.php)
- [ExternalSyncedBarang.php](file://app/Models/ExternalSyncedBarang.php)
- [SyncExternalBarangCommand.php](file://app/Console/Commands/SyncExternalBarangCommand.php)
- [2026_11_23_000001_create_external_item_mappings_table.php](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php)
- [2026_11_23_000002_create_external_synced_barangs_table.php](file://database/migrations/2026_11_23_000002_create_external_synced_barangs_table.php)
- [services.php](file://config/services.php)
- [ExternalBarangSyncServiceTest.php](file://tests/Feature/ExternalBarangSyncServiceTest.php)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)

## Introduction
This document explains the External Barang synchronization system that pulls finished helmet units from an external public API and records them as inventory movements into a configured warehouse. The central orchestrator is `ExternalBarangSyncService`. It coordinates:

- Fetching paginated unit data from the external API through `ExternalBarangApiService`.
- Filtering by status, skipping already synced items, and validating variant mappings via `ExternalItemMapping`.
- Recording stock increases using `StockMovementService`.
- Persisting sync audit records in `ExternalSyncedBarang`.
- Providing a console command entry point for manual or scheduled runs.

The service is designed to be idempotent, safe for repeated execution, and focused on production finish goods rather than registration-stage items.

## Project Structure
The synchronization feature spans services, models, migrations, configuration, a console command, and tests:

```mermaid
graph TB
Command["SyncExternalBarangCommand<br/>Console entry point"] --> SyncService["ExternalBarangSyncService<br/>Sync orchestrator"]
SyncService --> ApiService["ExternalBarangApiService<br/>HTTP client wrapper"]
SyncService --> StockService["StockMovementService<br/>Inventory movement writer"]
SyncService --> MappingModel["ExternalItemMapping<br/>External variant to internal item mapping"]
SyncService --> SyncedModel["ExternalSyncedBarang<br/>Sync audit record"]
ApiService --> ExternalAPI["External Public API<br/>/api/public/barang*"]
```

**Diagram sources**
- [SyncExternalBarangCommand.php:16-28](file://app/Console/Commands/SyncExternalBarangCommand.php#L16-L28)
- [ExternalBarangSyncService.php:14-19](file://app/Services/ExternalBarangSyncService.php#L14-L19)
- [ExternalBarangApiService.php:10-20](file://app/Services/ExternalBarangApiService.php#L10-L20)
- [StockMovementService.php:16-23](file://app/Services/StockMovementService.php#L16-L23)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

**Section sources**
- [ExternalBarangSyncService.php:14-242](file://app/Services/ExternalBarangSyncService.php#L14-L242)
- [ExternalBarangApiService.php:10-188](file://app/Services/ExternalBarangApiService.php#L10-L188)
- [StockMovementService.php:16-398](file://app/Services/StockMovementService.php#L16-L398)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)
- [SyncExternalBarangCommand.php:9-90](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L90)

## Core Components
- `ExternalBarangSyncService`: Orchestrates the full sync workflow, including pagination, filtering, validation, mapping, idempotency checks, stock recording, and result reporting.
- `ExternalBarangApiService`: Encapsulates HTTP calls to the external public API with base URL, timeout, error logging, and helper endpoints such as ping and variant discovery.
- `StockMovementService`: Records inventory movements and updates balances safely within database transactions.
- `ExternalItemMapping`: Maps external variant identifiers to internal items; used to determine whether a unit can be synchronized.
- `ExternalSyncedBarang`: Persists each successfully processed external unit for audit and deduplication.
- `SyncExternalBarangCommand`: Provides a user-friendly CLI interface to run sync jobs with options like limit, page handling, status filter, and dry-run mode.

**Section sources**
- [ExternalBarangSyncService.php:14-242](file://app/Services/ExternalBarangSyncService.php#L14-L242)
- [ExternalBarangApiService.php:10-188](file://app/Services/ExternalBarangApiService.php#L10-L188)
- [StockMovementService.php:16-398](file://app/Services/StockMovementService.php#L16-L398)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)
- [SyncExternalBarangCommand.php:9-90](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L90)

## Architecture Overview
The sync process follows a clear pipeline:

1. Command receives options and resolves the target warehouse.
2. Sync service loads active mappings and prepares counters.
3. For each page:
   - Build query parameters (page, limit, optional status and variant filters).
   - Call external API via `ExternalBarangApiService`.
   - Iterate units and apply business rules:
     - Skip excluded statuses.
     - Skip duplicates already recorded.
     - Validate variant mapping.
     - Optionally simulate in dry-run mode.
     - Record inventory movement and persisted sync record inside a transaction.
4. Return structured results with metrics and unmapped variant details.

```mermaid
sequenceDiagram
participant User as "User / Scheduler"
participant Command as "SyncExternalBarangCommand"
participant Sync as "ExternalBarangSyncService"
participant Api as "ExternalBarangApiService"
participant Stock as "StockMovementService"
participant DB as "Database"
User->>Command : Run sync : external-barang
Command->>Sync : sync(warehouse, options)
Sync->>DB : Load active mappings
loop Pages
Sync->>Api : getBarangList(params)
Api-->>Sync : {data, meta}
loop Units
Sync->>Sync : Filter status, check duplicates, validate mapping
alt Dry run or invalid
Sync->>Sync : Increment skip/unmapped counters
else Valid new unit
Sync->>Stock : record(incoming movement)
Stock->>DB : Create movement + update balance
Sync->>DB : Create ExternalSyncedBarang
end
end
end
Sync-->>Command : Result metrics
Command-->>User : Display table and message
```

**Diagram sources**
- [SyncExternalBarangCommand.php:33-87](file://app/Console/Commands/SyncExternalBarangCommand.php#L33-L87)
- [ExternalBarangSyncService.php:40-222](file://app/Services/ExternalBarangSyncService.php#L40-L222)
- [ExternalBarangApiService.php:33-55](file://app/Services/ExternalBarangApiService.php#L33-L55)
- [StockMovementService.php:23-60](file://app/Services/StockMovementService.php#L23-L60)

## Detailed Component Analysis

### ExternalBarangSyncService
Responsibilities:
- Parse sync options: excluded statuses, limit, page, all pages, dry run, user ID, status filter, variant filter.
- Load active mappings keyed by external variant ID.
- Paginate external API responses and iterate units.
- Apply business rules:
  - Exclude registration-like statuses.
  - Enforce idempotency by checking existing `ExternalSyncedBarang` records.
  - Require valid mapping to an internal item.
  - Support dry-run simulation without writing stock.
- Resolve occurrence date from external fields with fallback to current time.
- Record stock increase and persist sync audit record within a database transaction.
- Aggregate metrics and return a structured result array.

Key behaviors:
- Pagination: supports single-page and multi-page processing controlled by `all_pages`.
- Idempotency: prevents duplicate syncs per `kode_barang`.
- Validation: skips unmapped variants and reports them for operator action.
- Error isolation: individual unit failures are logged and do not abort the entire batch.

```mermaid
flowchart TD
Start(["sync() Entry"]) --> Options["Parse options<br/>excluded_statuses, limit, page, all_pages, dry_run, filters"]
Options --> LoadMappings["Load active mappings by external_variant_id"]
LoadMappings --> LoopPages{"More pages?"}
LoopPages --> |Yes| FetchPage["Call API getBarangList(page, limit, filters)"]
FetchPage --> HasUnits{"Has units?"}
HasUnits --> |No| End([Return result])
HasUnits --> |Yes| LoopUnits["Iterate units"]
LoopUnits --> Normalize["Normalize kodeBarang and status"]
Normalize --> CheckExcluded{"Status excluded?"}
CheckExcluded --> |Yes| SkipRegister["Skip register count++"] --> NextUnit["Next unit"]
CheckExcluded --> |No| CheckDuplicate{"Already synced?"}
CheckDuplicate --> |Yes| SkipDuplicate["Skip duplicate count++"] --> NextUnit
CheckDuplicate --> |No| CheckMapping{"Valid mapping exists?"}
CheckMapping --> |No| Unmapped["Increment unmapped and collect variant info"] --> NextUnit
CheckMapping --> |Yes| DryRun{"Dry run?"}
DryRun --> |Yes| CountDry["Count as synced"] --> NextUnit
DryRun --> |No| ResolveDate["Resolve occurred_at"]
ResolveDate --> Transaction["DB transaction"]
Transaction --> RecordMovement["Record incoming inventory movement"]
RecordMovement --> PersistSync["Create ExternalSyncedBarang"]
PersistSync --> Success["Increment synced_count"] --> NextUnit
NextUnit --> LoopUnits
LoopUnits --> LoopPages
End
```

**Diagram sources**
- [ExternalBarangSyncService.php:40-222](file://app/Services/ExternalBarangSyncService.php#L40-L222)

**Section sources**
- [ExternalBarangSyncService.php:40-242](file://app/Services/ExternalBarangSyncService.php#L40-L242)

### ExternalBarangApiService
Responsibilities:
- Configure base URL and timeout from environment/config.
- Provide methods for:
  - Listing barang units with pagination and filters.
  - Fetching detail by `kodeBarang`.
  - Fetching aggregated variant recap.
  - Discovering available variants for mapping setup.
  - Pinging the external API for health checks.
- Log warnings and errors for non-successful responses or connection issues.
- Return normalized arrays even when API fails to keep callers stable.

API communication patterns:
- Uses Laravel HTTP client with JSON accept header.
- Returns empty structures instead of throwing exceptions for failed requests, allowing sync to continue gracefully.

**Section sources**
- [ExternalBarangApiService.php:10-188](file://app/Services/ExternalBarangApiService.php#L10-L188)

### StockMovementService
Responsibilities relevant to sync:
- `record()` creates an inventory movement and applies delta to warehouse balance.
- Ensures positive quantity and uses database transactions for consistency.
- Locks balance rows during updates to prevent race conditions.

Integration with sync:
- Sync service calls `record()` with type indicating incoming production output.
- Movement reference uses external `kodeBarang`, linking audit records to inventory changes.

**Section sources**
- [StockMovementService.php:23-60](file://app/Services/StockMovementService.php#L23-L60)
- [StockMovementService.php:358-387](file://app/Services/StockMovementService.php#L358-L387)

### Data Models and Mappings

#### ExternalItemMapping
Purpose:
- Maps external variant identifiers to internal items.
- Stores external descriptive metadata useful for UI and diagnostics.
- Supports activation flag to control which mappings participate in sync.

Relationships:
- Belongs to internal `Item`.
- Has many `ExternalSyncedBarang` records linked by `external_variant_id`.

**Section sources**
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)

#### ExternalSyncedBarang
Purpose:
- Audit record for each successfully synced external unit.
- Tracks external identifiers, mapped internal item, warehouse, movement link, status, timestamps.

Relationships:
- Belongs to `Item`, `Warehouse`, `InventoryMovement`, and `ExternalItemMapping`.

**Section sources**
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

#### Database Schema Highlights
- `external_item_mappings`: Unique constraint on `external_variant_id`; indexes support efficient lookup during sync.
- `external_synced_barangs`: Unique constraint on `kode_barang`; indexes on `item_id`, `warehouse_id`, and `synced_at` support queries and reporting.

**Section sources**
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)
- [2026_11_23_000002_create_external_synced_barangs_table.php:14-37](file://database/migrations/2026_11_23_000002_create_external_synced_barangs_table.php#L14-L37)

### Console Command: SyncExternalBarangCommand
Responsibilities:
- Define command signature with options: warehouse, limit, all-pages, status, dry-run.
- Resolve active warehouse from config or first active warehouse if none provided.
- Invoke sync service with parsed options.
- Display metrics table and unmapped variants list.

Usage examples:
- Basic sync to default warehouse:
  - `php artisan sync:external-barang`
- Sync with explicit warehouse and limit:
  - `php artisan sync:external-barang --warehouse=1 --limit=50`
- Process all pages:
  - `php artisan sync:external-barang --all-pages`
- Filter by status:
  - `php artisan sync:external-barang --status=FINISHGOOD`
- Dry-run simulation:
  - `php artisan sync:external-barang --dry-run`

**Section sources**
- [SyncExternalBarangCommand.php:9-90](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L90)

## Dependency Analysis
The sync service depends on:

- `ExternalBarangApiService` for external data retrieval.
- `StockMovementService` for inventory updates.
- Eloquent models for mappings and sync records.
- Configuration for external API settings and defaults.

```mermaid
classDiagram
class ExternalBarangSyncService {
+sync(warehouse, options) array
-resolveDate(unit) Carbon
}
class ExternalBarangApiService {
+getBarangList(params) array
+getBarangDetail(kodeBarang) array|null
+getRekapVariant(tanggalAwal, tanggalAkhir) array
+fetchAvailableVariants() array
+ping() bool
}
class StockMovementService {
+record(attributes) InventoryMovement
}
class ExternalItemMapping {
+item() Item
+syncedBarangs() HasMany
}
class ExternalSyncedBarang {
+item() Item
+warehouse() Warehouse
+movement() InventoryMovement
+mapping() ExternalItemMapping
}
ExternalBarangSyncService --> ExternalBarangApiService : "uses"
ExternalBarangSyncService --> StockMovementService : "uses"
ExternalBarangSyncService --> ExternalItemMapping : "reads"
ExternalBarangSyncService --> ExternalSyncedBarang : "writes"
```

**Diagram sources**
- [ExternalBarangSyncService.php:14-242](file://app/Services/ExternalBarangSyncService.php#L14-L242)
- [ExternalBarangApiService.php:10-188](file://app/Services/ExternalBarangApiService.php#L10-L188)
- [StockMovementService.php:16-398](file://app/Services/StockMovementService.php#L16-L398)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

**Section sources**
- [ExternalBarangSyncService.php:14-242](file://app/Services/ExternalBarangSyncService.php#L14-L242)
- [ExternalBarangApiService.php:10-188](file://app/Services/ExternalBarangApiService.php#L10-L188)
- [StockMovementService.php:16-398](file://app/Services/StockMovementService.php#L16-L398)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

## Performance Considerations
- Pagination limits: The sync service caps limit at 100 to avoid large payloads and reduce memory pressure.
- All-pages mode: Use cautiously; it iterates multiple pages and may increase runtime and API load.
- Mapping cache: Active mappings are loaded once per sync run and keyed by external variant ID for fast lookup.
- Transaction scope: Each unit’s stock recording and sync record creation are wrapped in a transaction, ensuring atomicity but potentially increasing lock contention under high concurrency.
- Logging: Errors are logged per unit failure; consider log rotation and sampling for very large batches.
- Indexes: Database indexes on `external_synced_barangs.item_id`, `warehouse_id`, and `synced_at` improve query performance for reporting and deduplication checks.

Recommendations:
- Schedule sync jobs during off-peak hours.
- Use dry-run mode to validate configurations before committing stock changes.
- Monitor unmapped variants and maintain mappings proactively.
- Consider queueing sync jobs for large datasets to avoid request timeouts.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
Common issues and resolutions:

- No active warehouse found:
  - Ensure an active warehouse exists or pass `--warehouse=ID`.
  - Check configuration `EXTERNAL_BARANG_DEFAULT_WAREHOUSE_ID`.

- External API unreachable:
  - Verify `EXTERNAL_BARANG_API_URL` and network connectivity.
  - Use `ExternalBarangApiService::ping()` to test availability.
  - Review logs for connection errors.

- Excessive skipped register counts:
  - Adjust `excluded_statuses` in configuration if business rules change.
  - Confirm external API status values match expected formats.

- High unmapped variant count:
  - Configure mappings in `ExternalItemMapping` for missing external variants.
  - Use `ExternalBarangApiService::fetchAvailableVariants()` to discover variants.

- Duplicate sync attempts:
  - Idempotency is enforced by `kode_barang`; re-running sync will skip already synced units.
  - If you need to force re-sync, remove or adjust existing `ExternalSyncedBarang` records carefully.

- Inventory movement failures:
  - Inspect logs for stock recording errors.
  - Validate that the mapped item and warehouse exist and have sufficient constraints.

- Dry-run behavior:
  - Dry-run increments counters but does not write stock or sync records.
  - Use dry-run to validate filters and mappings before real execution.

**Section sources**
- [ExternalBarangSyncService.php:189-194](file://app/Services/ExternalBarangSyncService.php#L189-L194)
- [ExternalBarangApiService.php:42-54](file://app/Services/ExternalBarangApiService.php#L42-L54)
- [SyncExternalBarangCommand.php:35-45](file://app/Console/Commands/SyncExternalBarangCommand.php#L35-L45)
- [ExternalBarangSyncServiceTest.php:158-186](file://tests/Feature/ExternalBarangSyncServiceTest.php#L158-L186)

## Conclusion
The External Barang synchronization system provides a robust, idempotent mechanism to import finished helmet units from an external API into internal inventory. It emphasizes safety through transactions, clear filtering and validation, and comprehensive metrics reporting. Operators can configure warehouses, limits, filters, and dry-run modes via the console command, while developers can extend variant discovery and mapping workflows using the provided services and models. Proper maintenance of mappings and monitoring of logs and metrics ensures reliable operation at scale.

[No sources needed since this section summarizes without analyzing specific files]