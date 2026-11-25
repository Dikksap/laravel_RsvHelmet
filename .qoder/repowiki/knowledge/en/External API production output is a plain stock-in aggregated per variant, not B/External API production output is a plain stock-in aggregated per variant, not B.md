---
kind: design
name: External API production output is a plain stock-in aggregated per variant, not BOM-driven
source: session
category: adr
---

# External API production output is a plain stock-in aggregated per variant, not BOM-driven

_Source: coding plans from commit period d286f15 → abc0c48 — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
Before this period, `CreateInventoryMovement` recorded `in_production` from a free internal Item with optional BOM consumption via `StockMovementService::recordProductionOutput()`, while `ExternalBarangSyncService::sync()` pulled units from the external API and wrote one `in_production` movement per `kodeBarang` with no link to any `ProductionBatch`. The two paths were disconnected and the external path had no batch traceability.

## Decision drivers
- audit linkage of synced units to an internal `ProductionBatch`
- single aggregated movement per variant instead of one per unit
- no automatic BOM deduction for external input so existing manual BOM flow stays intact

## Considered options
- **Reuse `recordProductionOutput()` for external pulls** _(rejected)_ — pros: reuses existing BOM logic; single code path; cons: would create `out_production` component rows against material stock even though external output has no material receipt; couples external data source to internal BOM semantics
- **One `in_production` movement per external unit (existing behavior)** _(rejected)_ — pros: minimal change to service layer; cons: no aggregation, no shared movement reference, harder to reconcile per variant; still no batch linkage
- **Aggregate eligible units into one `TYPE_IN_PRODUCTION` movement per variant, persist `production_batch_id` on both the movement and each `ExternalSyncedBarang` audit row** — pros: one clean stock-in per variant, explicit batch ownership, idempotent by `kode_barang`, keeps manual BOM mode untouched behind a toggle; cons: adds `production_batch_id` column to `external_synced_barangs`, requires new Filament fields (`source_external`, `external_variant_id`, date range) and a preview endpoint

## Decision
Introduce `ExternalBarangSyncService::syncVariantOutput()` that paginates the external list API, filters excluded statuses / already-synced units / date range, aggregates eligible units into one `TYPE_IN_PRODUCTION` movement via `StockMovementService::record()`, and persists one `ExternalSyncedBarang` audit row per unit — all inside a single DB transaction. The UI exposes a `source_external` toggle in `InventoryMovementResource`; when on, it hides item/quantity/BOM fields and drives the call through the new service. Existing `recordProductionOutput()` remains available only in manual mode.

## Consequences
`external_synced_barangs` gains a nullable `production_batch_id` foreign key to `production_batches`. Both the global sync action and the console command accept an optional `--batch` flag so scheduled runs remain plain stock-ins. External output never deducts BOM materials; operators must use manual mode if they need component consumption. The `countEligibleUnits` helper powers live previews but does not itself write data.