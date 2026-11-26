---
kind: design
name: Require linked inventory_movement_id to treat external_synced_barangs as synced
source: session
category: adr
---

# Require linked inventory_movement_id to treat external_synced_barangs as synced

_Source: coding plans from commit period 2370198 → f95361f — records intent at planning time; the implementation may lag or differ._

**Status:** accepted

## Context
Orphaned `external_synced_barangs` rows (with `inventory_movement_id = NULL`) from pre-transactional deletes were treated as already synced by the global `sync()` path, preventing re-pull of units whose stock had been deleted. The variant/batch paths (`collectUnits()`) already guarded against this, but the global path did not.

## Decision drivers
- idempotency correctness
- data consistency between sync paths
- safe cleanup of legacy orphan rows

## Considered options
- **Add `whereNotNull('inventory_movement_id')` to the idempotency check in `sync()`** — pros: Minimal change; lets `updateOrCreate` re-link orphans to new movements without duplication; aligns with existing `collectUnits()` logic
- **Delete orphaned rows at sync time and retry** — pros: Self-healing on first run; cons: Adds write complexity inside a read path; risks race conditions during concurrent pulls

## Decision
Guard the `$alreadySynced` query in `ExternalBarangSyncService::sync()` with `whereNotNull('inventory_movement_id')`, and add a one-off migration deleting legacy orphaned rows. Orphaned rows are reclaimed by the existing `updateOrCreate` call inside the transaction, relinking them to the new movement.

## Consequences
The global sync path now behaves consistently with the variant/batch paths. Legacy orphans are purged once via migration; future deletions of movements will be cleaned up transactionally by `StockMovementService::delete()`. UI counts in `DaftarBarangEksternal::getGroupedData()` also exclude orphans.