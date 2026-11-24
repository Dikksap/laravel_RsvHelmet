---
kind: business_term
name: Business Glossary
category: business_term
scope:
    - '**'
---

### RSV Helmet
- Definition：The project codename and product name — a Laravel-based inventory and production management application for RSV Helmet.
- Aliases：RSV_Helmet、RSV Helmet Laravel

### barang
- Definition：Indonesian term for 'item/product' used throughout the domain model (Item, ExternalSyncedBarang, ExternalItemMapping) and the external API contract; refers to a sellable SKU that may have variants (product/style/color/size).
- Aliases：unit barang、item

### stock opname
- Definition：Inventory count/audit process modeled by StockOpname and StockOpnameLine entities, producing InventoryMovement records upon completion.
- Aliases：stock opname line、inventory count

### production batch
- Definition：A manufacturing run modeled by ProductionBatch, composed of finished items (ProductionBatchItem) and required materials (ProductionBatchMaterial), tracked via InventoryMovement.
- Aliases：batch、production batch item、production batch material

### item request
- Definition：An internal procurement/request workflow modeled by ItemRequest and ItemRequestLine, supporting per-line approval columns and triggering inventory movements when approved.
- Aliases：item request line、IR

### BOM builder
- Definition：Bill of Materials construction tool exposed as a Filament Page (`Pages/BomBuilder.php`), driven by ItemComponent relationships and the BomExplosionService.
- Aliases：bom、bill of materials

### external item mapping
- Definition：Linking between an internal Item and an external product from the External Barang API, stored in ExternalItemMapping and synchronized via ExternalBarangSyncService.
- Aliases：external mapping、item variant mapping

### warehouse
- Definition：Physical storage location entity (Warehouse model) referenced by inventory balances, stock opnames, and item requests; configurable per external barang via `EXTERNAL_BARANG_DEFAULT_WAREHOUSE_ID`.

### division
- Definition：Organizational unit attached to Items (division_id column) for department-level tracking or filtering within the inventory system.
