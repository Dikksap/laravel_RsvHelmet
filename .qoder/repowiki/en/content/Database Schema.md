# Database Schema

<cite>
**Referenced Files in This Document**
- [2026_11_13_000007_create_items_table.php](file://database/migrations/2026_11_13_000007_create_items_table.php)
- [2026_11_16_000001_create_warehouses_table.php](file://database/migrations/2026_11_16_000001_create_warehouses_table.php)
- [2026_11_16_000002_create_inventory_balances_table.php](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php)
- [2026_11_17_000002_create_production_batches_table.php](file://database/migrations/2026_11_17_000002_create_production_batches_table.php)
- [2026_11_17_000003_create_production_batch_items_table.php](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php)
- [2026_11_17_000004_create_production_batch_materials_table.php](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php)
- [2026_11_17_000005_create_inventory_movements_table.php](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php)
- [2026_11_18_000001_add_parent_movement_id_to_inventory_movements_table.php](file://database/migrations/2026_11_18_000001_add_parent_movement_id_to_inventory_movements_table.php)
- [2026_11_19_000002_create_stock_opnames_table.php](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php)
- [2026_11_19_000003_create_stock_opname_lines_table.php](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php)
- [2026_11_19_000004_add_stock_opname_id_to_inventory_movements_table.php](file://database/migrations/2026_11_19_000004_add_stock_opname_id_to_inventory_movements_table.php)
- [2026_11_19_000005_create_divisions_table.php](file://database/migrations/2026_11_19_000005_create_divisions_table.php)
- [2026_11_19_000007_create_item_requests_table.php](file://database/migrations/2026_11_19_000007_create_item_requests_table.php)
- [2026_11_19_000008_create_item_request_lines_table.php](file://database/migrations/2026_11_19_000008_create_item_request_lines_table.php)
- [2026_11_19_000011_add_item_request_id_to_inventory_movements_table.php](file://database/migrations/2026_11_19_000011_add_item_request_id_to_inventory_movements_table.php)
- [2026_11_19_000012_create_item_request_settings_table.php](file://database/migrations/2026_11_19_000012_create_item_request_settings_table.php)
- [2026_11_23_000001_create_external_item_mappings_table.php](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php)
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
This document describes the database schema for RSV Helmet, focusing on core inventory and production entities: Items, Warehouses, InventoryBalances, InventoryMovements, ProductionBatches, StockOpnames, ItemRequests, Divisions, and related supporting tables. It explains table structures, field definitions, data types, constraints, indexes, migration strategy, validation rules enforced at the database level, data lifecycle considerations, and guidance for extending the schema.

## Project Structure
The database schema is defined through Laravel migrations under database/migrations. The most relevant files for this documentation are listed above. Each migration creates or alters a table and defines relationships, constraints, and indexes.

```mermaid
graph TB
subgraph "Master Data"
ITEMS["items"]
WAREHOUSES["warehouses"]
DIVISIONS["divisions"]
UOMS["uoms"]
ITEM_TYPES["item_types"]
ITEM_CATEGORIES["item_categories"]
BRANDS["brands"]
end
subgraph "Inventory"
INV_BALANCES["inventory_balances"]
INV_MOVEMENTS["inventory_movements"]
end
subgraph "Production"
PROD_BATCHES["production_batches"]
PROD_BATCH_ITEMS["production_batch_items"]
PROD_BATCH_MATERIALS["production_batch_materials"]
end
subgraph "Stocktaking"
STOCK_OPNAMES["stock_opnames"]
STOCK_OPNAME_LINES["stock_opname_lines"]
end
subgraph "Procurement Requests"
ITEM_REQUESTS["item_requests"]
ITEM_REQUEST_LINES["item_request_lines"]
ITEM_REQUEST_SETTINGS["item_request_settings"]
end
subgraph "External Integration"
EXT_ITEM_MAPPINGS["external_item_mappings"]
end
ITEMS --> INV_BALANCES
WAREHOUSES --> INV_BALANCES
ITEMS --> INV_MOVEMENTS
WAREHOUSES --> INV_MOVEMENTS
PROD_BATCHES --> INV_MOVEMENTS
PROD_BATCHES --> PROD_BATCH_ITEMS
PROD_BATCHES --> PROD_BATCH_MATERIALS
ITEMS --> PROD_BATCH_ITEMS
ITEMS --> PROD_BATCH_MATERIALS
WAREHOUSES --> STOCK_OPNAMES
STOCK_OPNAMES --> STOCK_OPNAME_LINES
ITEMS --> STOCK_OPNAME_LINES
STOCK_OPNAMES --> INV_MOVEMENTS
DIVISIONS --> ITEM_REQUESTS
ITEM_REQUESTS --> ITEM_REQUEST_LINES
ITEMS --> ITEM_REQUEST_LINES
ITEM_REQUESTS --> INV_MOVEMENTS
WAREHOUSES --> ITEM_REQUEST_SETTINGS
WAREHOUSES --> ITEM_REQUEST_SETTINGS
ITEMS --> EXT_ITEM_MAPPINGS
```

**Diagram sources**
- [2026_11_13_000007_create_items_table.php:14-73](file://database/migrations/2026_11_13_000007_create_items_table.php#L14-L73)
- [2026_11_16_000001_create_warehouses_table.php:14-21](file://database/migrations/2026_11_16_000001_create_warehouses_table.php#L14-L21)
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)
- [2026_11_17_000002_create_production_batches_table.php:14-34](file://database/migrations/2026_11_17_000002_create_production_batches_table.php#L14-L34)
- [2026_11_17_000003_create_production_batch_items_table.php:14-26](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php#L14-L26)
- [2026_11_17_000004_create_production_batch_materials_table.php:14-26](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php#L14-L26)
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000003_create_stock_opname_lines_table.php:14-35](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php#L14-L35)
- [2026_11_19_000007_create_item_requests_table.php:14-27](file://database/migrations/2026_11_19_000007_create_item_requests_table.php#L14-L27)
- [2026_11_19_000008_create_item_request_lines_table.php:14-27](file://database/migrations/2026_11_19_000008_create_item_request_lines_table.php#L14-L27)
- [2026_11_19_000012_create_item_request_settings_table.php:14-25](file://database/migrations/2026_11_19_000012_create_item_request_settings_table.php#L14-L25)
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)

**Section sources**
- [2026_11_13_000007_create_items_table.php:14-73](file://database/migrations/2026_11_13_000007_create_items_table.php#L14-L73)
- [2026_11_16_000001_create_warehouses_table.php:14-21](file://database/migrations/2026_11_16_000001_create_warehouses_table.php#L14-L21)
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)
- [2026_11_17_000002_create_production_batches_table.php:14-34](file://database/migrations/2026_11_17_000002_create_production_batches_table.php#L14-L34)
- [2026_11_17_000003_create_production_batch_items_table.php:14-26](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php#L14-L26)
- [2026_11_17_000004_create_production_batch_materials_table.php:14-26](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php#L14-L26)
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000003_create_stock_opname_lines_table.php:14-35](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php#L14-L35)
- [2026_11_19_000007_create_item_requests_table.php:14-27](file://database/migrations/2026_11_19_000007_create_item_requests_table.php#L14-L27)
- [2026_11_19_000008_create_item_request_lines_table.php:14-27](file://database/migrations/2026_11_19_000008_create_item_request_lines_table.php#L14-L27)
- [2026_11_19_000012_create_item_request_settings_table.php:14-25](file://database/migrations/2026_11_19_000012_create_item_request_settings_table.php#L14-L25)
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)

## Core Components
- Items: Master product catalog with classification, dimensions, pricing, and variant configuration.
- Warehouses: Physical storage locations.
- InventoryBalances: Per-item per-warehouse stock levels.
- InventoryMovements: Immutable audit trail of stock changes (receipts, issues, transfers, adjustments).
- ProductionBatches: Manufacturing orders linking planned outputs and required materials.
- StockOpnames: Periodic physical counts with line-level details and adjustments.
- ItemRequests: Procurement requests with lines and optional approval workflow.
- ExternalItemMappings: Mapping between internal items and external system variants.

Key characteristics:
- Quantities use decimal(15,3) to support fractional units.
- Foreign keys enforce referential integrity across master and transactional tables.
- Indexes optimize common queries by item, warehouse, time, and status.

**Section sources**
- [2026_11_13_000007_create_items_table.php:14-73](file://database/migrations/2026_11_13_000007_create_items_table.php#L14-L73)
- [2026_11_16_000001_create_warehouses_table.php:14-21](file://database/migrations/2026_11_16_000001_create_warehouses_table.php#L14-L21)
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_17_000002_create_production_batches_table.php:14-34](file://database/migrations/2026_11_17_000002_create_production_batches_table.php#L14-L34)
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000007_create_item_requests_table.php:14-27](file://database/migrations/2026_11_19_000007_create_item_requests_table.php#L14-L27)
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)

## Architecture Overview
The schema separates master data from transactional records and provides an immutable movement ledger. Production batches define planned outputs and material requirements; stock opnames capture physical counts; item requests drive procurement flows. All movements reference the originating business event when applicable.

```mermaid
erDiagram
ITEMS {
uuid id PK
uuid parent_item_id
boolean has_variants
string item_code UK
string barcode UK
string item_name
text description
int item_type_id FK
int category_id FK
int brand_id FK
int base_uom_id FK
decimal weight
int weight_uom_id FK
decimal length
decimal width
decimal height
int dimension_uom_id FK
decimal base_price
decimal standard_cost
boolean is_stockable
boolean is_sellable
boolean is_purchasable
boolean is_active
datetime created_at
datetime updated_at
}
WAREHOUSES {
int id PK
string code UK
string name
string address
boolean is_active
datetime created_at
datetime updated_at
}
INVENTORY_BALANCES {
int id PK
int warehouse_id FK
uuid item_id FK
decimal quantity_on_hand
decimal quantity_reserved
decimal minimum_quantity
timestamp last_counted_at
datetime created_at
datetime updated_at
}
INVENTORY_MOVEMENTS {
int id PK
string type
uuid item_id FK
int warehouse_id FK
decimal quantity
int production_batch_id FK
uuid transfer_group_id
int parent_movement_id FK
int stock_opname_id FK
int item_request_id FK
datetime occurred_at
string reference
text note
int created_by FK
datetime created_at
datetime updated_at
}
PRODUCTION_BATCHES {
int id PK
string batch_code UK
string status
int material_warehouse_id FK
int output_warehouse_id FK
date started_at
date finished_at
text notes
int created_by FK
datetime created_at
datetime updated_at
}
PRODUCTION_BATCH_ITEMS {
int id PK
int production_batch_id FK
uuid item_id FK
decimal quantity_planned
datetime created_at
datetime updated_at
}
PRODUCTION_BATCH_MATERIALS {
int id PK
int production_batch_id FK
uuid item_id FK
decimal quantity_required
datetime created_at
datetime updated_at
}
STOCK_OPNAMES {
int id PK
string code UK
int warehouse_id FK
string status
text note
int created_by FK
datetime completed_at
int completed_by FK
datetime created_at
datetime updated_at
}
STOCK_OPNAME_LINES {
int id PK
int stock_opname_id FK
uuid item_id FK
decimal system_quantity
decimal counted_quantity
decimal adjustment_quantity
datetime counted_at
int counted_by FK
text note
datetime created_at
datetime updated_at
}
DIVISIONS {
int id PK
string name UK
boolean is_active
datetime created_at
datetime updated_at
}
ITEM_REQUESTS {
int id PK
string code UK
string requester_name
int division_id FK
text note
string status
datetime created_at
datetime updated_at
}
ITEM_REQUEST_LINES {
int id PK
int item_request_id FK
uuid item_id FK
decimal quantity
string note
datetime created_at
datetime updated_at
}
ITEM_REQUEST_SETTINGS {
int id PK
int warehouse_id FK
int destination_warehouse_id FK
datetime created_at
datetime updated_at
}
EXTERNAL_ITEM_MAPPINGS {
uuid id PK
uuid item_id FK
int external_variant_id UK
string external_variant_code
string external_product_name
string external_style_name
string external_color_name
string external_size_name
boolean is_active
datetime created_at
datetime updated_at
}
ITEMS ||--o{ INVENTORY_BALANCES : "per item per warehouse"
WAREHOUSES ||--o{ INVENTORY_BALANCES : "per warehouse"
ITEMS ||--o{ INVENTORY_MOVEMENTS : "movement source"
WAREHOUSES ||--o{ INVENTORY_MOVEMENTS : "movement location"
PRODUCTION_BATCHES ||--o{ INVENTORY_MOVEMENTS : "source of receipts/issues"
PRODUCTION_BATCHES ||--o{ PRODUCTION_BATCH_ITEMS : "planned outputs"
PRODUCTION_BATCHES ||--o{ PRODUCTION_BATCH_MATERIALS : "required materials"
ITEMS ||--o{ PRODUCTION_BATCH_ITEMS : "outputs"
ITEMS ||--o{ PRODUCTION_BATCH_MATERIALS : "materials"
WAREHOUSES ||--o{ STOCK_OPNAMES : "counted location"
STOCK_OPNAMES ||--o{ STOCK_OPNAME_LINES : "lines"
ITEMS ||--o{ STOCK_OPNAME_LINES : "counted items"
STOCK_OPNAMES ||--o{ INVENTORY_MOVEMENTS : "adjustments"
DIVISIONS ||--o{ ITEM_REQUESTS : "requests"
ITEM_REQUESTS ||--o{ ITEM_REQUEST_LINES : "requested items"
ITEMS ||--o{ ITEM_REQUEST_LINES : "requested items"
ITEM_REQUESTS ||--o{ INVENTORY_MOVEMENTS : "fulfillment"
WAREHOUSES ||--o{ ITEM_REQUEST_SETTINGS : "defaults"
ITEMS ||--o{ EXTERNAL_ITEM_MAPPINGS : "external mapping"
```

**Diagram sources**
- [2026_11_13_000007_create_items_table.php:14-73](file://database/migrations/2026_11_13_000007_create_items_table.php#L14-L73)
- [2026_11_16_000001_create_warehouses_table.php:14-21](file://database/migrations/2026_11_16_000001_create_warehouses_table.php#L14-L21)
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_17_000002_create_production_batches_table.php:14-34](file://database/migrations/2026_11_17_000002_create_production_batches_table.php#L14-L34)
- [2026_11_17_000003_create_production_batch_items_table.php:14-26](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php#L14-L26)
- [2026_11_17_000004_create_production_batch_materials_table.php:14-26](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php#L14-L26)
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000003_create_stock_opname_lines_table.php:14-35](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php#L14-L35)
- [2026_11_19_000007_create_item_requests_table.php:14-27](file://database/migrations/2026_11_19_000007_create_item_requests_table.php#L14-L27)
- [2026_11_19_000008_create_item_request_lines_table.php:14-27](file://database/migrations/2026_11_19_000008_create_item_request_lines_table.php#L14-L27)
- [2026_11_19_000012_create_item_request_settings_table.php:14-25](file://database/migrations/2026_11_19_000012_create_item_request_settings_table.php#L14-L25)
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)

## Detailed Component Analysis

### Items
- Purpose: Central product catalog with classification, dimensions, pricing, and variant hierarchy.
- Key fields:
  - id: UUID primary key.
  - parent_item_id: Self-referencing foreign key enabling parent/variant relationships.
  - has_variants: Boolean flag indicating presence of variants.
  - item_code, barcode: Unique identifiers for lookup and scanning.
  - item_name, description: Human-readable attributes.
  - item_type_id, category_id, brand_id: Classification references.
  - base_uom_id, weight_uom_id, dimension_uom_id: Units of measure references.
  - weight, length, width, height: Physical attributes.
  - base_price, standard_cost: Pricing and costing.
  - is_stockable, is_sellable, is_purchasable, is_active: Behavior flags.
- Constraints:
  - Unique indexes on item_code and barcode.
  - Foreign keys to item_types, item_categories, brands, uoms.
  - Self-referencing cascade delete for parent_item_id.
- Indexes:
  - Unique constraints ensure identity uniqueness.

**Section sources**
- [2026_11_13_000007_create_items_table.php:14-73](file://database/migrations/2026_11_13_000007_create_items_table.php#L14-L73)

### Warehouses
- Purpose: Define physical storage locations.
- Key fields:
  - id: Auto-increment primary key.
  - code: Unique short code.
  - name, address: Descriptive fields.
  - is_active: Soft-active flag.
- Constraints:
  - Unique index on code.

**Section sources**
- [2026_11_16_000001_create_warehouses_table.php:14-21](file://database/migrations/2026_11_16_000001_create_warehouses_table.php#L14-L21)

### InventoryBalances
- Purpose: Snapshot of on-hand quantities per item per warehouse.
- Key fields:
  - warehouse_id, item_id: Composite unique key ensures one balance row per item per warehouse.
  - quantity_on_hand: Current stock level.
  - quantity_reserved: Reserved stock (note: later removed via migration).
  - minimum_quantity: Reorder threshold.
  - last_counted_at: Last physical count timestamp.
- Constraints:
  - Unique composite index on (warehouse_id, item_id).
  - Foreign keys to warehouses and items.
- Indexes:
  - Additional index on item_id for performance.

Notes:
- A subsequent migration removes quantity_reserved from this table.

**Section sources**
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)

### InventoryMovements
- Purpose: Immutable ledger of all stock changes with rich context.
- Key fields:
  - type: Movement type (e.g., receipt, issue, transfer, adjustment).
  - item_id, warehouse_id: Dimensions of change.
  - quantity: Signed or absolute quantity depending on type semantics.
  - production_batch_id: Optional link to production events.
  - transfer_group_id: Groups related outbound/inbound legs.
  - parent_movement_id: Hierarchical grouping of related movements.
  - stock_opname_id: Link to stocktaking adjustments.
  - item_request_id: Link to fulfillment of requests.
  - occurred_at: Business timestamp of the event.
  - reference, note: Auditability.
  - created_by: System user who recorded the movement.
- Constraints:
  - Foreign keys to items, warehouses, users, production_batches, stock_opnames, item_requests.
- Indexes:
  - Composite index on (item_id, warehouse_id, occurred_at).
  - Indexes on production_batch_id and transfer_group_id.

**Section sources**
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_18_000001_add_parent_movement_id_to_inventory_movements_table.php:14-20](file://database/migrations/2026_11_18_000001_add_parent_movement_id_to_inventory_movements_table.php#L14-L20)
- [2026_11_19_000004_add_stock_opname_id_to_inventory_movements_table.php:14-20](file://database/migrations/2026_11_19_000004_add_stock_opname_id_to_inventory_movements_table.php#L14-L20)
- [2026_11_19_000011_add_item_request_id_to_inventory_movements_table.php:14-20](file://database/migrations/2026_11_19_000011_add_item_request_id_to_inventory_movements_table.php#L14-L20)

### ProductionBatches
- Purpose: Manufacturing orders capturing planned outputs and material requirements.
- Key fields:
  - batch_code: Unique identifier.
  - status: Workflow state (e.g., draft).
  - material_warehouse_id, output_warehouse_id: Source and destination warehouses.
  - started_at, finished_at: Dates.
  - notes: Free-form context.
  - created_by: User who created the batch.
- Constraints:
  - Unique index on batch_code.
  - Foreign keys to warehouses and users.

**Section sources**
- [2026_11_17_000002_create_production_batches_table.php:14-34](file://database/migrations/2026_11_17_000002_create_production_batches_table.php#L14-L34)

### ProductionBatchItems and ProductionBatchMaterials
- Purpose: Detail lines for planned outputs and required materials.
- Key fields:
  - production_batch_id: Parent batch.
  - item_id: Referenced item.
  - quantity_planned / quantity_required: Planned amounts.
- Constraints:
  - Unique composite index on (production_batch_id, item_id) prevents duplicates.
  - Foreign keys to production_batches and items.

**Section sources**
- [2026_11_17_000003_create_production_batch_items_table.php:14-26](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php#L14-L26)
- [2026_11_17_000004_create_production_batch_materials_table.php:14-26](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php#L14-L26)

### StockOpnames and StockOpnameLines
- Purpose: Physical inventory counting process with line-level detail and adjustments.
- Key fields:
  - stock_opnames: code, warehouse_id, status, note, created_by, completed_at, completed_by.
  - stock_opname_lines: stock_opname_id, item_id, system_quantity, counted_quantity, adjustment_quantity, counted_at, counted_by, note.
- Constraints:
  - Unique composite index on (stock_opname_id, item_id).
  - Index on item_id for performance.
  - Foreign keys to warehouses, items, users, stock_opnames.

**Section sources**
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000003_create_stock_opname_lines_table.php:14-35](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php#L14-L35)

### ItemRequests and ItemRequestLines
- Purpose: Internal procurement request workflow with optional approval columns added later.
- Key fields:
  - item_requests: code, requester_name, division_id, note, status.
  - item_request_lines: item_request_id, item_id, quantity, note.
- Constraints:
  - Unique composite index on (item_request_id, item_id).
  - Index on status for filtering.
  - Foreign keys to divisions, items, item_requests.

**Section sources**
- [2026_11_19_000007_create_item_requests_table.php:14-27](file://database/migrations/2026_11_19_000007_create_item_requests_table.php#L14-L27)
- [2026_11_19_000008_create_item_request_lines_table.php:14-27](file://database/migrations/2026_11_19_000008_create_item_request_lines_table.php#L14-L27)

### ItemRequestSettings
- Purpose: Default settings for item requests, including origin and destination warehouses.
- Key fields:
  - warehouse_id, destination_warehouse_id: Nullable defaults.

**Section sources**
- [2026_11_19_000012_create_item_request_settings_table.php:14-25](file://database/migrations/2026_11_19_000012_create_item_request_settings_table.php#L14-L25)

### ExternalItemMappings
- Purpose: Map internal items to external system variants.
- Key fields:
  - id: UUID primary key.
  - item_id: Internal item reference.
  - external_variant_id: Unique external identifier.
  - external_variant_code, external_product_name, external_style_name, external_color_name, external_size_name: External descriptors.
  - is_active: Active mapping flag.

**Section sources**
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)

## Dependency Analysis
- High cohesion within domains:
  - Inventory domain: items, warehouses, inventory_balances, inventory_movements.
  - Production domain: production_batches, production_batch_items, production_batch_materials.
  - Stocktaking domain: stock_opnames, stock_opname_lines.
  - Procurement domain: item_requests, item_request_lines, item_request_settings.
  - External integration: external_item_mappings.
- Coupling points:
  - inventory_movements acts as a central hub connecting production, stocktaking, and procurement events.
  - items and warehouses are referenced by multiple transactional tables.

```mermaid
graph LR
ITEMS["items"] --> INV_MOVEMENTS["inventory_movements"]
WAREHOUSES["warehouses"] --> INV_MOVEMENTS
PROD_BATCHES["production_batches"] --> INV_MOVEMENTS
STOCK_OPNAMES["stock_opnames"] --> INV_MOVEMENTS
ITEM_REQUESTS["item_requests"] --> INV_MOVEMENTS
ITEMS --> PROD_BATCH_ITEMS["production_batch_items"]
ITEMS --> PROD_BATCH_MATERIALS["production_batch_materials"]
PROD_BATCHES --> PROD_BATCH_ITEMS
PROD_BATCHES --> PROD_BATCH_MATERIALS
ITEMS --> INV_BALANCES["inventory_balances"]
WAREHOUSES --> INV_BALANCES
ITEMS --> STOCK_OPNAME_LINES["stock_opname_lines"]
STOCK_OPNAMES --> STOCK_OPNAME_LINES
ITEMS --> ITEM_REQUEST_LINES["item_request_lines"]
ITEM_REQUESTS --> ITEM_REQUEST_LINES
```

**Diagram sources**
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_17_000002_create_production_batches_table.php:14-34](file://database/migrations/2026_11_17_000002_create_production_batches_table.php#L14-L34)
- [2026_11_17_000003_create_production_batch_items_table.php:14-26](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php#L14-L26)
- [2026_11_17_000004_create_production_batch_materials_table.php:14-26](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php#L14-L26)
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000003_create_stock_opname_lines_table.php:14-35](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php#L14-L35)
- [2026_11_19_000007_create_item_requests_table.php:14-27](file://database/migrations/2026_11_19_000007_create_item_requests_table.php#L14-L27)
- [2026_11_19_000008_create_item_request_lines_table.php:14-27](file://database/migrations/2026_11_19_000008_create_item_request_lines_table.php#L14-L27)

**Section sources**
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000007_create_item_requests_table.php:14-27](file://database/migrations/2026_11_19_000007_create_item_requests_table.php#L14-L27)

## Performance Considerations
- Indexing strategy:
  - inventory_movements uses composite index on (item_id, warehouse_id, occurred_at) to accelerate stock history queries.
  - Additional indexes on production_batch_id and transfer_group_id improve lookups by batch and grouped transfers.
  - stock_opname_lines includes index on item_id for faster joins.
  - item_requests indexes status for filtering.
- Denormalization trade-offs:
  - inventory_balances provides fast read access to current stock per item per warehouse but must be kept consistent with inventory_movements.
- Data volume growth:
  - inventory_movements will grow continuously; consider partitioning by occurred_at if query patterns allow.
- Concurrency:
  - Use transactions around multi-step operations that update balances and create movements to maintain consistency.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
- Referential integrity errors:
  - Ensure referenced items, warehouses, users, and batches exist before creating movements or balances.
- Duplicate entries:
  - Prevent duplicate lines in production batches and stock opname lines using composite unique constraints.
- Inconsistent stock levels:
  - Validate that inventory_movements sum matches inventory_balances; reconcile discrepancies via stock opnames.
- Missing context links:
  - When movements lack production_batch_id, stock_opname_id, or item_request_id, trace back to application logic to ensure proper linkage.

**Section sources**
- [2026_11_17_000005_create_inventory_movements_table.php:14-41](file://database/migrations/2026_11_17_000005_create_inventory_movements_table.php#L14-L41)
- [2026_11_17_000003_create_production_batch_items_table.php:14-26](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php#L14-L26)
- [2026_11_17_000004_create_production_batch_materials_table.php:14-26](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php#L14-L26)
- [2026_11_19_000003_create_stock_opname_lines_table.php:14-35](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php#L14-L35)

## Conclusion
The RSV Helmet database schema enforces strong referential integrity, supports fractional quantities, and maintains an immutable movement ledger. It cleanly separates master data from transactional records and provides robust indexing for common queries. The design accommodates production planning, stocktaking, and procurement workflows while allowing extensibility through additional mappings and settings.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices

### Migration Strategy and Versioning
- Migrations are timestamp-prefixed and ordered chronologically.
- Additive changes only: new tables and columns are added; existing columns are not renamed in place.
- Column removals are explicit via dedicated migrations (e.g., removing quantity_reserved from inventory_balances).
- Relationships are introduced gradually, adding foreign keys where needed (e.g., parent_movement_id, stock_opname_id, item_request_id on inventory_movements).

**Section sources**
- [2026_11_18_000001_add_parent_movement_id_to_inventory_movements_table.php:14-20](file://database/migrations/2026_11_18_000001_add_parent_movement_id_to_inventory_movements_table.php#L14-L20)
- [2026_11_19_000004_add_stock_opname_id_to_inventory_movements_table.php:14-20](file://database/migrations/2026_11_19_000004_add_stock_opname_id_to_inventory_movements_table.php#L14-L20)
- [2026_11_19_000011_add_item_request_id_to_inventory_movements_table.php:14-20](file://database/migrations/2026_11_19_000011_add_item_request_id_to_inventory_movements_table.php#L14-L20)

### Data Validation Rules and Business Constraints
- Uniqueness:
  - items.item_code, items.barcode, warehouses.code, production_batches.batch_code, stock_opnames.code, external_item_mappings.external_variant_id.
- Referential integrity:
  - Foreign keys enforce valid relationships across items, warehouses, users, production_batches, stock_opnames, item_requests.
- Domain constraints:
  - inventory_balances unique per (warehouse_id, item_id).
  - production_batch_items and production_batch_materials unique per (production_batch_id, item_id).
  - stock_opname_lines unique per (stock_opname_id, item_id).
- Numeric precision:
  - Decimal(15,3) used for all quantity-related fields to support fractional units.

**Section sources**
- [2026_11_13_000007_create_items_table.php:14-73](file://database/migrations/2026_11_13_000007_create_items_table.php#L14-L73)
- [2026_11_16_000001_create_warehouses_table.php:14-21](file://database/migrations/2026_11_16_000001_create_warehouses_table.php#L14-L21)
- [2026_11_16_000002_create_inventory_balances_table.php:14-30](file://database/migrations/2026_11_16_000002_create_inventory_balances_table.php#L14-L30)
- [2026_11_17_000002_create_production_batches_table.php:14-34](file://database/migrations/2026_11_17_000002_create_production_batches_table.php#L14-L34)
- [2026_11_17_000003_create_production_batch_items_table.php:14-26](file://database/migrations/2026_11_17_000003_create_production_batch_items_table.php#L14-L26)
- [2026_11_17_000004_create_production_batch_materials_table.php:14-26](file://database/migrations/2026_11_17_000004_create_production_batch_materials_table.php#L14-L26)
- [2026_11_19_000002_create_stock_opnames_table.php:14-34](file://database/migrations/2026_11_19_000002_create_stock_opnames_table.php#L14-L34)
- [2026_11_19_000003_create_stock_opname_lines_table.php:14-35](file://database/migrations/2026_11_19_000003_create_stock_opname_lines_table.php#L14-L35)
- [2026_11_23_000001_create_external_item_mappings_table.php:14-27](file://database/migrations/2026_11_23_000001_create_external_item_mappings_table.php#L14-L27)

### Data Lifecycle, Archival, and Backup Considerations
- Lifecycle:
  - Master data (items, warehouses, divisions) is long-lived and relatively static.
  - Transactional data (movements, batch lines, opname lines) grows continuously.
- Archival:
  - Consider archiving old inventory_movements partitions by occurred_at to keep active tables lean.
  - Retain historical stock opnames and their lines for auditability.
- Backup:
  - Back up both master and transactional schemas regularly.
  - Ensure point-in-time recovery for movement-ledger continuity.

[No sources needed since this section provides general guidance]

### Extending the Schema
- Adding new movement types:
  - Extend inventory_movements.type values and add any necessary contextual columns.
- Adding new attributes to items:
  - Add nullable columns to items to preserve backward compatibility.
- New operational domains:
  - Create new tables with clear foreign keys to items and warehouses.
  - Link new operations to inventory_movements for auditability.
- External integrations:
  - Extend external_item_mappings or create new mapping tables as needed.

[No sources needed since this section provides general guidance]