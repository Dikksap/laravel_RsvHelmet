# External System Integration

<cite>
**Referenced Files in This Document**
- [ExternalBarangApiService.php](file://app/Services/ExternalBarangApiService.php)
- [ExternalBarangSyncService.php](file://app/Services/ExternalBarangSyncService.php)
- [ExternalItemMapping.php](file://app/Models/ExternalItemMapping.php)
- [ExternalSyncedBarang.php](file://app/Models/ExternalSyncedBarang.php)
- [SyncExternalBarangCommand.php](file://app/Console/Commands/SyncExternalBarangCommand.php)
- [2026_11_23_000001_create_external_item_mappings_table.php](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php)
- [2026_11_23_000002_create_external_synced_barangs_table.php](file://database/migrations/2026_11_23_000002_create_external_synced_barangs_table.php)
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
10. [Appendices](#appendices)

## Introduction
This document explains the External System Integration module used to synchronize external “barang” (items) data into the local inventory system. It covers:
- The API client for communicating with an external barang endpoint.
- The synchronization service that transforms, validates, and persists synchronized stock movements.
- The mapping model that connects external variants to internal items.
- The sync tracking model that records what has been synchronized.
- The console command that automates synchronization runs.

The integration is designed for safety and auditability: it avoids duplicate entries, skips unfinished or excluded statuses, requires explicit variant-to-item mappings, and records every successful sync as a warehouse inventory movement.

## Project Structure
The external integration lives under application services, models, console commands, and database migrations:
- Services: HTTP client and synchronization logic.
- Models: Mapping configuration and sync history.
- Console: Automated command for running sync jobs.
- Migrations: Database schema for mapping and sync tracking.

```mermaid
graph TB
subgraph "Integration Layer"
Api["ExternalBarangApiService"]
Sync["ExternalBarangSyncService"]
Cmd["SyncExternalBarangCommand"]
end
subgraph "Domain Models"
Mapping["ExternalItemMapping"]
Synced["ExternalSyncedBarang"]
end
subgraph "Local Inventory"
Movement["InventoryMovement"]
Warehouse["Warehouse"]
Item["Item"]
end
subgraph "External System"
ExtApi["External Barang API"]
end
Cmd --> Sync
Sync --> Api
Sync --> Mapping
Sync --> Synced
Sync --> Movement
Sync --> Warehouse
Api --> ExtApi
Mapping --> Item
Synced --> Item
Synced --> Warehouse
Synced --> Movement
```

**Diagram sources**
- [ExternalBarangApiService.php:10-186](file://app/Services/ExternalBarangApiService.php#L10-L186)
- [ExternalBarangSyncService.php:14-240](file://app/Services/ExternalBarangSyncService.php#L14-L240)
- [SyncExternalBarangCommand.php:9-89](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L89)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

**Section sources**
- [ExternalBarangApiService.php:10-186](file://app/Services/ExternalBarangApiService.php#L10-L186)
- [ExternalBarangSyncService.php:14-240](file://app/Services/ExternalBarangSyncService.php#L14-L240)
- [SyncExternalBarangCommand.php:9-89](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L89)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

## Core Components
- ExternalBarangApiService: A thin HTTP client wrapper around the external barang API. It provides methods to list barang, fetch details, retrieve aggregated variant recap, extract available variants, and ping connectivity.
- ExternalBarangSyncService: Orchestrates synchronization by paginating through external data, filtering by status, checking idempotency, applying variant-to-item mappings, recording inventory movements, and persisting sync records.
- ExternalItemMapping: Stores the mapping between an external variant identifier and a local item, including metadata such as external code, product, style, color, and size names.
- ExternalSyncedBarang: Tracks each successfully synchronized external unit, linking it to the local item, warehouse, inventory movement, and original external identifiers.
- SyncExternalBarangCommand: A Laravel console command that exposes options for warehouse selection, pagination control, status filtering, dry-run simulation, and displays summary metrics.

**Section sources**
- [ExternalBarangApiService.php:10-186](file://app/Services/ExternalBarangApiService.php#L10-L186)
- [ExternalBarangSyncService.php:14-240](file://app/Services/ExternalBarangSyncService.php#L14-L240)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)
- [SyncExternalBarangCommand.php:9-89](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L89)

## Architecture Overview
The integration follows a layered approach:
- Command layer: Accepts user input and delegates to the sync service.
- Service layer: Coordinates API calls, business rules, and persistence.
- Data layer: Uses Eloquent models and database tables for mapping and sync tracking.
- External layer: Communicates with the external barang API over HTTP.

```mermaid
sequenceDiagram
participant User as "Operator"
participant Cmd as "SyncExternalBarangCommand"
participant Sync as "ExternalBarangSyncService"
participant Api as "ExternalBarangApiService"
participant DB as "Local Database"
participant Ext as "External Barang API"
User->>Cmd : Run sync command with options
Cmd->>Sync : sync(warehouse, options)
Sync->>Api : getBarangList(page, limit, filters)
Api->>Ext : GET /api/public/barang
Ext-->>Api : JSON response
Api-->>Sync : {data, meta}
Sync->>DB : Check existing synced records
Sync->>DB : Load active mappings
Sync->>DB : Record inventory movement (if not dry run)
Sync->>DB : Persist ExternalSyncedBarang record
Sync-->>Cmd : Summary metrics
Cmd-->>User : Display results and unmapped variants
```

**Diagram sources**
- [SyncExternalBarangCommand.php:33-87](file://app/Console/Commands/SyncExternalBarangCommand.php#L33-L87)
- [ExternalBarangSyncService.php:40-221](file://app/Services/ExternalBarangSyncService.php#L40-L221)
- [ExternalBarangApiService.php:33-55](file://app/Services/ExternalBarangApiService.php#L33-L55)

## Detailed Component Analysis

### ExternalBarangApiService
Responsibilities:
- Configure base URL and timeout from configuration.
- Provide methods for listing barang, fetching detail by kodeBarang, retrieving variant recap, extracting unique variants, and pinging connectivity.
- Use Laravel HTTP client with JSON acceptance and consistent error logging.

Key behaviors:
- getBarangList returns a normalized structure with data and meta arrays; logs warnings on non-successful responses and returns safe defaults.
- getBarangDetail returns null on failure or missing data.
- getRekapVariant aggregates variant data across date ranges and returns a stable shape even on errors.
- fetchAvailableVariants builds a deduplicated set of external variants using recap first, then falls back to the barang list if needed.
- ping performs a lightweight health check against the external API.

Error handling:
- All network and parsing exceptions are caught and logged; methods return safe fallback values to prevent downstream failures.

Authentication:
- No authentication headers are applied in this client; it targets public endpoints. If the external system changes to require authentication, headers should be added in the client builder method.

Retry mechanisms:
- No built-in retry policy exists. For transient failures, consider wrapping calls with a retry strategy at the caller level or introducing a dedicated HTTP retry middleware.

```mermaid
flowchart TD
Start(["API Call"]) --> BuildClient["Build HTTP Client<br/>base URL + timeout + JSON accept"]
BuildClient --> SendRequest["Send Request to External API"]
SendRequest --> Success{"Response Successful?"}
Success --> |Yes| ParseJson["Parse JSON Response"]
Success --> |No| LogWarning["Log Warning/Error"]
ParseJson --> ReturnData["Return Normalized Data"]
LogWarning --> ReturnFallback["Return Safe Fallback"]
ReturnData --> End(["Done"])
ReturnFallback --> End
```

**Diagram sources**
- [ExternalBarangApiService.php:16-20](file://app/Services/ExternalBarangApiService.php#L16-L20)
- [ExternalBarangApiService.php:33-55](file://app/Services/ExternalBarangApiService.php#L33-L55)
- [ExternalBarangApiService.php:181-186](file://app/Services/ExternalBarangApiService.php#L181-L186)

**Section sources**
- [ExternalBarangApiService.php:10-186](file://app/Services/ExternalBarangApiService.php#L10-L186)

### ExternalBarangSyncService
Responsibilities:
- Paginate external barang data with configurable limits and optional all-pages mode.
- Filter out excluded statuses (default includes REGISTER).
- Enforce idempotency by skipping already-synced kodeBarang.
- Require active variant-to-item mappings; otherwise, track unmapped variants.
- Record inventory movements and create sync records within a database transaction.
- Resolve occurrence dates from multiple fields with fallback to current time.

Processing flow:
- Load active mappings keyed by external_variant_id.
- Iterate pages until either no more data or all pages are processed.
- For each unit:
  - Skip empty kodeBarang.
  - Skip excluded statuses.
  - Skip duplicates via ExternalSyncedBarang lookup.
  - Validate mapping existence; collect unmapped variants.
  - In dry-run mode, increment counters without writing.
  - Otherwise, record inventory movement and persist sync record inside a transaction.

Error handling:
- Individual unit processing failures are caught and logged; they do not abort the entire batch.

Idempotency:
- Duplicate prevention is enforced per kodeBarang.

Date resolution:
- Attempts to parse finishGoodAt, tanggal, createdAt; falls back to now() if parsing fails.

```mermaid
flowchart TD
Start(["Sync Start"]) --> LoadMappings["Load Active Mappings"]
LoadMappings --> PageLoop["Paginate External Data"]
PageLoop --> ForEachUnit["For Each Unit"]
ForEachUnit --> ValidateKode["Validate kodeBarang"]
ValidateKode --> StatusCheck{"Status Excluded?"}
StatusCheck --> |Yes| SkipRegister["Skip Register"]
StatusCheck --> |No| Idempotency["Check Existing Sync"]
Idempotency --> |Exists| SkipDuplicate["Skip Duplicate"]
Idempotency --> |New| MapCheck{"Mapping Exists?"}
MapCheck --> |No| Unmapped["Track Unmapped Variant"]
MapCheck --> |Yes| DryRun{"Dry Run?"}
DryRun --> |Yes| CountOnly["Increment Counters Only"]
DryRun --> |No| Record["Record Movement + Sync Record"]
Record --> NextUnit["Next Unit"]
Unmapped --> NextUnit
SkipRegister --> NextUnit
SkipDuplicate --> NextUnit
CountOnly --> NextUnit
NextUnit --> MorePages{"More Pages?"}
MorePages --> |Yes| PageLoop
MorePages --> |No| Finish(["Sync Complete"])
```

**Diagram sources**
- [ExternalBarangSyncService.php:40-221](file://app/Services/ExternalBarangSyncService.php#L40-L221)
- [ExternalBarangSyncService.php:227-240](file://app/Services/ExternalBarangSyncService.php#L227-L240)

**Section sources**
- [ExternalBarangSyncService.php:14-240](file://app/Services/ExternalBarangSyncService.php#L14-L240)

### ExternalItemMapping Model
Purpose:
- Maps an external variant identifier to a local item.
- Stores auxiliary metadata like external code and descriptive names for product, style, color, and size.
- Supports activation toggling via is_active.

Relationships:
- Belongs to Item.
- Has many synced barang records linked by external_variant_id.

Casts:
- external_variant_id cast to integer.
- is_active cast to boolean.

Configuration example:
- Create a mapping where external_variant_id corresponds to a local item_id and mark it active.
- Optionally fill external_variant_code and descriptive fields to aid identification in UIs and reports.

**Section sources**
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)

### ExternalSyncedBarang Model
Purpose:
- Records each successfully synchronized external unit.
- Links to local item, warehouse, and inventory movement.
- Preserves external identifiers and timestamps for auditability.

Relationships:
- Belongs to Item, Warehouse, InventoryMovement, and ExternalItemMapping (via external_variant_id).

Casts:
- external_id, external_variant_id, external_batch_id cast to integers.
- external_occurred_at and synced_at cast to datetime.

Audit fields:
- status captures the external status at sync time.
- external_occurred_at preserves the source event timestamp when available.
- synced_at marks when the local system recorded the sync.

**Section sources**
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)
- [2026_11_23_000002_create_external_synced_barangs_table.php:14-37](file://database/migrations/2026_11_23_000002_create_external_synced_barangs_table.php#L14-L37)

### SyncExternalBarangCommand
Purpose:
- Provides a CLI interface to run synchronization with flexible options.

Options:
- --warehouse: Target warehouse ID; defaults to configured default or first active warehouse.
- --limit: Records per request (max 100).
- --all-pages: Fetch all paginated pages.
- --status: Filter by specific external status.
- --dry-run: Simulate without creating inventory movements.

Behavior:
- Validates presence of an active warehouse.
- Delegates to ExternalBarangSyncService with parsed options.
- Displays a summary table of metrics and lists unmapped variants if any.

Example usage patterns:
- Basic sync to default warehouse:
  - php artisan sync:external-barang
- Sync with custom limit and all pages:
  - php artisan sync:external-barang --limit=50 --all-pages
- Dry-run simulation:
  - php artisan sync:external-barang --dry-run
- Filter by status:
  - php artisan sync:external-barang --status=PRODUCTION
- Target specific warehouse:
  - php artisan sync:external-barang --warehouse=123

**Section sources**
- [SyncExternalBarangCommand.php:9-89](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L89)

## Dependency Analysis
Component relationships:
- SyncExternalBarangCommand depends on ExternalBarangSyncService.
- ExternalBarangSyncService depends on ExternalBarangApiService and StockMovementService.
- ExternalBarangSyncService reads ExternalItemMapping and writes ExternalSyncedBarang.
- ExternalSyncedBarang references Item, Warehouse, InventoryMovement, and ExternalItemMapping.
- ExternalItemMapping references Item.

```mermaid
classDiagram
class ExternalBarangApiService {
+getBarangList(params) array
+getBarangDetail(kodeBarang) ?array
+getRekapVariant(tanggalAwal, tanggalAkhir) array
+fetchAvailableVariants() array
+ping() bool
}
class ExternalBarangSyncService {
+sync(warehouse, options) array
-resolveDate(unit) Carbon
}
class SyncExternalBarangCommand {
+handle(syncService) int
}
class ExternalItemMapping {
+item() BelongsTo
+syncedBarangs() HasMany
}
class ExternalSyncedBarang {
+item() BelongsTo
+warehouse() BelongsTo
+movement() BelongsTo
+mapping() BelongsTo
}
class Item
class Warehouse
class InventoryMovement
SyncExternalBarangCommand --> ExternalBarangSyncService : "uses"
ExternalBarangSyncService --> ExternalBarangApiService : "calls"
ExternalBarangSyncService --> ExternalItemMapping : "reads"
ExternalBarangSyncService --> ExternalSyncedBarang : "writes"
ExternalSyncedBarang --> Item : "belongs to"
ExternalSyncedBarang --> Warehouse : "belongs to"
ExternalSyncedBarang --> InventoryMovement : "belongs to"
ExternalItemMapping --> Item : "belongs to"
```

**Diagram sources**
- [ExternalBarangApiService.php:10-186](file://app/Services/ExternalBarangApiService.php#L10-L186)
- [ExternalBarangSyncService.php:14-240](file://app/Services/ExternalBarangSyncService.php#L14-L240)
- [SyncExternalBarangCommand.php:9-89](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L89)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

**Section sources**
- [ExternalBarangApiService.php:10-186](file://app/Services/ExternalBarangApiService.php#L10-L186)
- [ExternalBarangSyncService.php:14-240](file://app/Services/ExternalBarangSyncService.php#L14-L240)
- [SyncExternalBarangCommand.php:9-89](file://app/Console/Commands/SyncExternalBarangCommand.php#L9-L89)
- [ExternalItemMapping.php:23-47](file://app/Models/ExternalItemMapping.php#L23-L47)
- [ExternalSyncedBarang.php:23-60](file://app/Models/ExternalSyncedBarang.php#L23-L60)

## Performance Considerations
- Pagination control: Use --limit to cap page sizes; combine with --all-pages only when necessary to avoid excessive requests.
- Filtering: Apply --status or variant-specific filters to reduce payload size and processing time.
- Mapping efficiency: Ensure mappings are indexed by external_variant_id; the sync service loads them once per run.
- Transaction scope: Each successful unit is persisted in its own transaction; large batches may benefit from batching strategies or queueing.
- Logging: Excessive logging can impact performance; ensure log levels are appropriate for production environments.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
Common issues and resolutions:
- External API unreachable:
  - Symptoms: ping returns false; getBarangList returns empty data; logs show connection errors.
  - Actions: Verify base_url and timeout configuration; check network connectivity and firewall rules; confirm external API availability.
- No active warehouse found:
  - Symptoms: Command exits with an error indicating no active warehouse.
  - Actions: Create or activate a warehouse; pass --warehouse with a valid active ID.
- Units skipped due to excluded status:
  - Symptoms: High skipped_register_count.
  - Actions: Adjust excluded_statuses configuration or use --status to include desired statuses.
- Many unmapped variants:
  - Symptoms: Command reports unmapped variants with external IDs and metadata.
  - Actions: Create ExternalItemMapping records for those variants and mark them active.
- Duplicate sync attempts:
  - Symptoms: High skipped_duplicate_count.
  - Actions: Confirm that ExternalSyncedBarang contains the same kodeBarang; investigate why duplicates appear in external data.
- Date parsing failures:
  - Symptoms: external_occurred_at falls back to current time.
  - Actions: Inspect external data fields (finishGoodAt, tanggal, createdAt) and normalize formats upstream.

Monitoring sync status:
- Review ExternalSyncedBarang records for recent syncs, statuses, and timestamps.
- Use inventory movement queries to verify stock increases for target warehouses.
- Check logs for warnings and errors during API calls and individual unit processing.

**Section sources**
- [ExternalBarangApiService.php:33-79](file://app/Services/ExternalBarangApiService.php#L33-L79)
- [ExternalBarangSyncService.php:40-221](file://app/Services/ExternalBarangSyncService.php#L40-L221)
- [SyncExternalBarangCommand.php:33-87](file://app/Console/Commands/SyncExternalBarangCommand.php#L33-L87)

## Conclusion
The External System Integration module provides a robust, auditable pipeline for synchronizing external barang data into local inventory. It emphasizes safety through status filtering, idempotency checks, mandatory mappings, and transactional persistence. Operators can automate runs via the console command, configure mappings through the domain model, and monitor outcomes using sync records and inventory movements. Future enhancements could include authenticated API support, retry policies, and advanced scheduling or queuing for large-scale operations.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices

### Configuration Reference
- Base URL and timeout:
  - services.external_barang.base_url
  - services.external_barang.timeout
- Default warehouse:
  - services.external_barang.default_warehouse_id
- Excluded statuses:
  - services.external_barang.excluded_statuses

These values are consumed by the API client and sync service to determine connectivity, request behavior, and filtering rules.

**Section sources**
- [ExternalBarangApiService.php:16-20](file://app/Services/ExternalBarangApiService.php#L16-L20)
- [ExternalBarangSyncService.php:42-53](file://app/Services/ExternalBarangSyncService.php#L42-L53)
- [SyncExternalBarangCommand.php:35-39](file://app/Console/Commands/SyncExternalBarangCommand.php#L35-L39)