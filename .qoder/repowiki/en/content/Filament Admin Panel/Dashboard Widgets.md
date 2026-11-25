# Dashboard Widgets

<cite>
**Referenced Files in This Document**
- [OutOfStockItemsTable.php](file://app/Filament/Widgets/OutOfStockItemsTable.php)
- [RecentMovementsTable.php](file://app/Filament/Widgets/RecentMovementsTable.php)
- [Dashboard.php](file://app/Filament/Pages/Dashboard.php)
- [InventoryBalance.php](file://app/Models/InventoryBalance.php)
- [InventoryMovement.php](file://app/Models/InventoryMovement.php)
- [InventoryBalanceResource.php](file://app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php)
</cite>

## Update Summary
**Changes Made**
- Complete replacement of production monitoring widgets with inventory-focused widgets
- Removed documentation for BatchProgressChart, BatchProgressTable, BatchStatusChart, ProductionOverview, and RecentCompletedBatchesTable
- Added documentation for OutOfStockItemsTable and RecentMovementsTable widgets
- Updated architecture to focus on inventory management instead of production batch tracking
- Revised all diagrams and examples to reflect the new inventory system

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
This document explains the dashboard widgets that provide inventory management insights through real-time stock monitoring and movement tracking. It covers:
- Inventory-focused widgets replacing production monitoring functionality
- Table widgets for displaying out-of-stock items and recent inventory movements
- Real-time inventory status tracking with stock level monitoring
- Widget lifecycle methods, data fetching strategies, and responsive design considerations
- Examples of creating custom inventory widgets and integrating with inventory models

The implementation uses Filament table widgets backed by inventory models to provide comprehensive inventory visibility and control.

## Project Structure
The dashboard is composed of:
- A Filament dashboard page that hosts inventory widgets
- Table widgets for monitoring stock levels and tracking inventory movements
- Inventory models defining stock balances and movement types
- Resource classes providing reusable components and business logic

```mermaid
graph TB
subgraph "Filament Pages"
D["Dashboard.php"]
end
subgraph "Filament Widgets"
OOST["OutOfStockItemsTable.php"]
RMT["RecentMovementsTable.php"]
end
subgraph "Domain Models"
IB["InventoryBalance.php"]
IM["InventoryMovement.php"]
end
subgraph "Resources"
IBR["InventoryBalanceResource.php"]
end
D --> OOST
D --> RMT
OOST --> IB
OOST --> IBR
RMT --> IM
```

**Diagram sources**
- [Dashboard.php:1-16](file://app/Filament/Pages/Dashboard.php#L1-L16)
- [OutOfStockItemsTable.php:1-64](file://app/Filament/Widgets/OutOfStockItemsTable.php#L1-L64)
- [RecentMovementsTable.php:1-79](file://app/Filament/Widgets/RecentMovementsTable.php#L1-L79)
- [InventoryBalance.php:1-71](file://app/Models/InventoryBalance.php#L1-L71)
- [InventoryMovement.php:1-196](file://app/Models/InventoryMovement.php#L1-L196)
- [InventoryBalanceResource.php:1-445](file://app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php#L1-L445)

**Section sources**
- [Dashboard.php:1-16](file://app/Filament/Pages/Dashboard.php#L1-L16)

## Core Components
- **OutOfStockItemsTable**: Displays items with zero or low stock levels, including item details, warehouse information, stock quantities, and stock status badges.
- **RecentMovementsTable**: Shows the most recent inventory movements with type classification, quantity tracking, and associated batch information.
- **InventoryBalance**: Model representing stock levels per warehouse-item combination with computed stock status attributes.
- **InventoryMovement**: Model tracking all inventory changes with type classification, directional indicators, and relationship mappings.
- **InventoryBalanceResource**: Provides reusable components like stock status columns and filters used across the application.

Key responsibilities:
- Stock monitoring and alerting through out-of-stock detection
- Movement tracking and visualization with directional indicators
- Status computation and presentation through computed attributes
- Reusable UI components through resource methods

**Section sources**
- [OutOfStockItemsTable.php:1-64](file://app/Filament/Widgets/OutOfStockItemsTable.php#L1-L64)
- [RecentMovementsTable.php:1-79](file://app/Filament/Widgets/RecentMovementsTable.php#L1-L79)
- [InventoryBalance.php:1-71](file://app/Models/InventoryBalance.php#L1-L71)
- [InventoryMovement.php:1-196](file://app/Models/InventoryMovement.php#L1-L196)
- [InventoryBalanceResource.php:65-80](file://app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php#L65-L80)

## Architecture Overview
The dashboard follows a clean separation between presentation (widgets), domain models, and business logic:
- Presentation layer: Filament table widgets render inventory data
- Domain layer: Inventory models provide relationships and computed attributes
- Business logic: Resource classes encapsulate reusable components and validation

```mermaid
sequenceDiagram
participant User as "User"
participant Page as "Dashboard.php"
participant Widget as "Inventory Widgets"
participant Model as "Inventory Models"
User->>Page : Open dashboard
Page->>Widget : Render inventory widgets
Widget->>Model : Query stock levels and movements
Model-->>Widget : Inventory data with computed attributes
Widget-->>User : Tables showing stock status and movements
```

**Diagram sources**
- [Dashboard.php:1-16](file://app/Filament/Pages/Dashboard.php#L1-L16)
- [OutOfStockItemsTable.php:1-64](file://app/Filament/Widgets/OutOfStockItemsTable.php#L1-L64)
- [RecentMovementsTable.php:1-79](file://app/Filament/Widgets/RecentMovementsTable.php#L1-L79)
- [InventoryBalance.php:1-71](file://app/Models/InventoryBalance.php#L1-L71)
- [InventoryMovement.php:1-196](file://app/Models/InventoryMovement.php#L1-L196)

## Detailed Component Analysis

### OutOfStockItemsTable Widget
Purpose:
- Monitor items with zero or critically low stock levels
- Provide quick access to items requiring immediate attention
- Display stock status with visual indicators and actionable insights

Key features:
- Filters items where quantity_on_hand ≤ 0 OR quantity_on_hand ≤ minimum_quantity
- Shows item code, name, warehouse, stock quantities, and computed stock status
- Includes header action to view all inventory balances
- Uses pagination disabled for focused display of critical items only

Data flow:
- Queries InventoryBalance model with complex filtering conditions
- Eager loads related item and warehouse relationships
- Applies sorting by stock level and minimum quantity thresholds

```mermaid
flowchart TD
Start(["Render OutOfStockItemsTable"]) --> Query["Query InventoryBalance with stock filters"]
Query --> Load["Eager load item and warehouse relationships"]
Load --> Filter["Apply stock status logic (out/low)"]
Filter --> Display["Display with stock status badges"]
```

**Diagram sources**
- [OutOfStockItemsTable.php:19-62](file://app/Filament/Widgets/OutOfStockItemsTable.php#L19-L62)
- [InventoryBalance.php:47-64](file://app/Models/InventoryBalance.php#L47-L64)

**Section sources**
- [OutOfStockItemsTable.php:1-64](file://app/Filament/Widgets/OutOfStockItemsTable.php#L1-L64)
- [InventoryBalance.php:47-64](file://app/Models/InventoryBalance.php#L47-L64)

### RecentMovementsTable Widget
Purpose:
- Track and visualize recent inventory movements
- Show incoming vs outgoing transactions with color-coded indicators
- Provide context about associated production batches and reference documents

Key features:
- Displays last 8 inventory movements ordered by occurrence time
- Color-codes movement types (green for incoming, red for outgoing)
- Shows formatted quantities with locale-aware number formatting
- Includes optional batch reference column for traceability

Data flow:
- Queries InventoryMovement model with eager loading
- Applies movement type classification and directional logic
- Formats quantities based on movement direction (incoming/outgoing)

```mermaid
flowchart TD
Start(["Render RecentMovementsTable"]) --> Query["Query InventoryMovement with relationships"]
Query --> Classify["Classify movement types and directions"]
Classify --> Format["Format quantities and dates"]
Format --> Display["Display with color-coded indicators"]
```

**Diagram sources**
- [RecentMovementsTable.php:19-77](file://app/Filament/Widgets/RecentMovementsTable.php#L19-L77)
- [InventoryMovement.php:143-174](file://app/Models/InventoryMovement.php#L143-L174)

**Section sources**
- [RecentMovementsTable.php:1-79](file://app/Filament/Widgets/RecentMovementsTable.php#L1-L79)
- [InventoryMovement.php:143-174](file://app/Models/InventoryMovement.php#L143-L174)

### Inventory Balance Model
Purpose:
- Represent stock levels for specific items in specific warehouses
- Compute stock status based on quantity thresholds
- Provide relationships to related entities

Key features:
- Decimal precision casting for accurate stock calculations
- Computed stock_status attribute determining 'out', 'low', or 'ok' status
- Relationships to Item and Warehouse models
- Scope method for low stock queries

```mermaid
classDiagram
class InventoryBalance {
+warehouse_id : int
+item_id : int
+quantity_on_hand : decimal
+minimum_quantity : decimal
+last_counted_at : datetime
+stock_status() string
+scopeLowStock() Builder
}
class Item {
<<model>>
}
class Warehouse {
<<model>>
}
InventoryBalance --> Item : belongsTo
InventoryBalance --> Warehouse : belongsTo
```

**Diagram sources**
- [InventoryBalance.php:1-71](file://app/Models/InventoryBalance.php#L1-L71)

**Section sources**
- [InventoryBalance.php:1-71](file://app/Models/InventoryBalance.php#L1-L71)

### Inventory Movement Model
Purpose:
- Track all inventory changes with detailed metadata
- Classify movement types and directions
- Provide relationships to source documents and entities

Key features:
- Multiple movement type constants (purchase, production, transfer, adjustment)
- Directional classification methods (isIncoming, isOutgoingType)
- Signed quantity calculation for net movement tracking
- Relationships to Item, Warehouse, ProductionBatch, and other entities

```mermaid
classDiagram
class InventoryMovement {
+type : string
+quantity : decimal
+occurred_at : datetime
+isIncoming() : bool
+getSignedQuantityAttribute() : float
+item() : BelongsTo
+warehouse() : BelongsTo
+productionBatch() : BelongsTo
}
class Item {
<<model>>
}
class Warehouse {
<<model>>
}
class ProductionBatch {
<<model>>
}
InventoryMovement --> Item : belongsTo
InventoryMovement --> Warehouse : belongsTo
InventoryMovement --> ProductionBatch : belongsTo
```

**Diagram sources**
- [InventoryMovement.php:1-196](file://app/Models/InventoryMovement.php#L1-L196)

**Section sources**
- [InventoryMovement.php:1-196](file://app/Models/InventoryMovement.php#L1-L196)

### Inventory Balance Resource
Purpose:
- Provide reusable components for inventory balance displays
- Define stock status visualization and filtering logic
- Encapsulate business rules for stock management

Key features:
- Reusable stock status column component with badge styling
- Stock status filter for querying by stock condition
- Form components for inventory management
- Bulk operations for warehouse transfers

**Section sources**
- [InventoryBalanceResource.php:65-101](file://app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php#L65-L101)

## Dependency Analysis
Component relationships:
- Widgets depend on inventory models for data and resource classes for reusable components
- Inventory models define relationships and computed attributes used throughout the system
- Dashboard page orchestrates widget rendering without direct data dependencies

```mermaid
graph LR
Dashboard["Dashboard.php"] --> OutOfStockItemsTable["OutOfStockItemsTable.php"]
Dashboard --> RecentMovementsTable["RecentMovementsTable.php"]
OutOfStockItemsTable --> InventoryBalance["InventoryBalance.php"]
OutOfStockItemsTable --> InventoryBalanceResource["InventoryBalanceResource.php"]
RecentMovementsTable --> InventoryMovement["InventoryMovement.php"]
InventoryBalance --> Item["Item.php"]
InventoryBalance --> Warehouse["Warehouse.php"]
InventoryMovement --> Item
InventoryMovement --> Warehouse
InventoryMovement --> ProductionBatch["ProductionBatch.php"]
```

**Diagram sources**
- [Dashboard.php:1-16](file://app/Filament/Pages/Dashboard.php#L1-L16)
- [OutOfStockItemsTable.php:1-64](file://app/Filament/Widgets/OutOfStockItemsTable.php#L1-L64)
- [RecentMovementsTable.php:1-79](file://app/Filament/Widgets/RecentMovementsTable.php#L1-L79)
- [InventoryBalance.php:1-71](file://app/Models/InventoryBalance.php#L1-L71)
- [InventoryMovement.php:1-196](file://app/Models/InventoryMovement.php#L1-L196)
- [InventoryBalanceResource.php:1-445](file://app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php#L1-L445)

**Section sources**
- [Dashboard.php:1-16](file://app/Filament/Pages/Dashboard.php#L1-L16)

## Performance Considerations
- Use eager loading (`with`) to prevent N+1 queries when displaying related data
- Limit result sets to reduce payload size (e.g., limit(8) for dashboard widgets)
- Disable pagination for focused dashboard views to avoid unnecessary overhead
- Leverage database-level filtering and sorting where possible
- Use computed attributes sparingly as they execute on every record access
- Consider caching frequently accessed inventory data if performance becomes an issue

## Troubleshooting Guide
Common issues and resolutions:
- **Missing stock status**: Ensure InventoryBalance::stock_status computed attribute is properly defined and returns correct values ('out', 'low', 'ok').
- **Incorrect movement classification**: Verify InventoryMovement::incomingTypes() and outgoingTypes() arrays match expected movement types.
- **Locale formatting issues**: Confirm app()->getLocale() is set correctly and Number::format is used consistently for quantity display.
- **Relationship loading problems**: Check that eager loading is properly configured in widget queries to avoid N+1 query issues.

**Section sources**
- [InventoryBalance.php:47-64](file://app/Models/InventoryBalance.php#L47-L64)
- [InventoryMovement.php:143-174](file://app/Models/InventoryMovement.php#L143-L174)

## Conclusion
The dashboard widgets provide comprehensive inventory management capabilities through focused table displays. The architecture separates concerns between presentation (widgets), domain data (models), and reusable components (resources). With careful use of eager loading, computed attributes, and clear model relationships, the system remains maintainable and performant while providing essential inventory visibility and control.

## Appendices

### Widget Lifecycle Methods Reference
- TableWidget:
  - table(Table $table): Defines query, columns, filters, actions, sorting, and pagination settings.
  - $sort: Controls widget ordering within the dashboard layout.
  - $columnSpan: Determines horizontal space allocation in grid layouts.

**Section sources**
- [OutOfStockItemsTable.php:13-17](file://app/Filament/Widgets/OutOfStockItemsTable.php#L13-L17)
- [RecentMovementsTable.php:13-17](file://app/Filament/Widgets/RecentMovementsTable.php#L13-L17)

### Creating Custom Inventory Widgets
Steps:
- Extend Filament's TableWidget base class for inventory-related displays.
- Implement the table() method to define queries, columns, and interactions.
- Use inventory models (InventoryBalance, InventoryMovement) for data access.
- Leverage resource classes for reusable components like stock status columns.
- Configure layout properties ($sort, $columnSpan) and interactive behaviors.

Example references:
- Out-of-stock monitoring pattern: [OutOfStockItemsTable.php:1-64](file://app/Filament/Widgets/OutOfStockItemsTable.php#L1-L64)
- Movement tracking pattern: [RecentMovementsTable.php:1-79](file://app/Filament/Widgets/RecentMovementsTable.php#L1-L79)
- Stock status component usage: [InventoryBalanceResource.php:65-80](file://app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php#L65-L80)

**Section sources**
- [OutOfStockItemsTable.php:1-64](file://app/Filament/Widgets/OutOfStockItemsTable.php#L1-L64)
- [RecentMovementsTable.php:1-79](file://app/Filament/Widgets/RecentMovementsTable.php#L1-L79)
- [InventoryBalanceResource.php:65-80](file://app/Filament/Resources/InventoryBalances/InventoryBalanceResource.php#L65-L80)

### Integrating External Data Sources
Approach:
- Create service classes to fetch and normalize external inventory data.
- Map external data to internal inventory models and relationships.
- Expose new datasets via custom widgets or extend existing ones.
- Apply caching at the service layer to mitigate external API latency.

Guidance:
- Keep external integrations isolated in services to preserve widget simplicity.
- Validate and sanitize external payloads before processing.
- Handle errors gracefully and fallback to cached or default values.
- Maintain data consistency through proper transaction handling.